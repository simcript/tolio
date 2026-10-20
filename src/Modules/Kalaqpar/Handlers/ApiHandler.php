<?php

namespace Src\Modules\Kalaqpar\Handlers;

use Closure;
use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;
use Pano\Kernel\HttpStatusEnum;
use Src\Modules\Kalaqpar\Exceptions\KalaqparException;
use Src\Modules\Kalaqpar\KalaqparFactory;
use Src\Modules\Kalaqpar\Services\GameEngine;
use Src\Modules\Kalaqpar\Services\GameState;
use Src\Modules\Kalaqpar\Services\GuestService;
use Src\Modules\Kalaqpar\Services\RateLimiter;

/**
 * JSON API behind the game UI (/kalaqpar/state, /kalaqpar/rooms, …).
 * Every POST passes guest identity, module-scoped CSRF, and a rate limit;
 * every failure is a Kalaqpar domain error rendered as stable JSON.
 */
final class ApiHandler extends BaseHandler
{
    public const STATE_LIMIT_MIN = 120;
    public const CREATE_LIMIT_MIN = 4;
    public const JOIN_LIMIT_MIN = 10;
    public const GUESS_LIMIT_MIN = 30;
    public const PROFILE_LIMIT_MIN = 5;
    public const LEADERBOARD_LIMIT_MIN = 60;

    private ?array $graph = null;

    private ?GuestService $guests = null;

    /** Open rooms refresh for the lobby page. */
    public function rooms(): Response
    {
        $guest = $this->guestService()->resolve($this->request);

        return $this->wrap(function (array $graph) use ($guest): Response {
            $graph['limiter']->assertLimit((int) $guest['id'], 'rooms', self::STATE_LIMIT_MIN, 60, time());

            return Response::json([
                'ok' => true,
                'rooms' => array_map(static fn (array $room): array => [
                    'code' => $room['code'],
                    'name' => $room['name'],
                    'status' => $room['status'],
                    'rounds_total' => (int) $room['rounds_total'],
                    'current_round_number' => (int) $room['current_round_number'],
                    'players' => (int) $room['player_count'],
                ], $graph['rooms']->openRooms()),
            ]);
        });
    }

    /** Poll endpoint: room state + lazy engine pulse. */
    public function state(): Response
    {
        $guest = $this->guestService()->resolve($this->request);

        $roomQueries = $this->request->getQueries();
        $code = strtoupper(trim((string) ($roomQueries['room'] ?? '')));
        $after = (int) ($roomQueries['after'] ?? 0);

        if ($code === '') {
            return $this->fail(new KalaqparException(
                KalaqparException::VALIDATION,
                'کد اتاق مشخص نشده است.',
                400,
            ));
        }

        return $this->wrap(function (array $graph) use ($guest, $code, $after): Response {
            $graph['limiter']->assertLimit((int) $guest['id'], 'state', self::STATE_LIMIT_MIN, 60, time());

            // Lazy engine pulse: keeps reveals/timeouts moving even without
            // the CLI worker, race-safe because ticks serialize per room.
            $graph['engine']->tick(time());

            /** @var GameState $state */
            $state = $graph['state'];

            return Response::json($state->forRoom($code, $guest, $after));
        });
    }

    public function leaderboard(): Response
    {
        $guest = $this->guestService()->resolve($this->request);

        return $this->wrap(function (array $graph) use ($guest): Response {
            $graph['limiter']->assertLimit((int) $guest['id'], 'leaderboard', self::LEADERBOARD_LIMIT_MIN, 60, time());

            return Response::json([
                'ok' => true,
                'leaderboard' => $graph['guests']->leaderboard(10),
            ]);
        });
    }

    public function createRoom(): Response
    {
        return $this->post('room.create', self::CREATE_LIMIT_MIN,
            function (array $graph, array $guest): Response {
                /** @var GameEngine $engine */
                $engine = $graph['engine'];

                $room = $engine->createRoom($guest, $this->body(), time());

                return Response::json([
                    'ok' => true,
                    'room' => $room['code'],
                    'name' => $room['name'],
                    'rounds' => $room['rounds'],
                ], HttpStatusEnum::CREATED);
            });
    }

    public function joinRoom(): Response
    {
        return $this->post('room.join', self::JOIN_LIMIT_MIN,
            function (array $graph, array $guest): Response {
                $code = $this->requiredRoom();
                $graph['engine']->joinRoom($guest, $code, time());

                return Response::json(['ok' => true, 'room' => $code]);
            });
    }

    public function leaveRoom(): Response
    {
        return $this->post('room.leave', self::JOIN_LIMIT_MIN,
            function (array $graph, array $guest): Response {
                $code = $this->requiredRoom();
                $graph['engine']->leaveRoom($guest, $code, time());

                return Response::json(['ok' => true, 'room' => $code]);
            });
    }

    public function startGame(): Response
    {
        return $this->post('room.start', self::JOIN_LIMIT_MIN,
            function (array $graph, array $guest): Response {
                $code = $this->requiredRoom();
                $graph['engine']->startGame($guest, $code, time());

                return Response::json(['ok' => true, 'room' => $code]);
            });
    }

    public function guess(): Response
    {
        return $this->post('guess', self::GUESS_LIMIT_MIN,
            function (array $graph, array $guest): Response {
                $body = $this->body();
                $code = strtoupper(trim((string) ($body['room'] ?? '')));
                $raw = (string) ($body['guess'] ?? '');

                if ($code === '') {
                    throw new KalaqparException(KalaqparException::VALIDATION, 'کد اتاق مشخص نشده است.', 400);
                }

                $result = $graph['engine']->guess($guest, $code, $raw, time());

                return Response::json(['ok' => true] + $result);
            });
    }

    public function profile(): Response
    {
        return $this->post('profile', self::PROFILE_LIMIT_MIN,
            function (array $graph, array $guest): Response {
                $name = $this->guestService()->rename($guest, (string) ($this->body()['display_name'] ?? ''));

                return Response::json(['ok' => true, 'display_name' => $name]);
            });
    }

    // ------------------------------------------------------------------
    // Shared plumbing
    // ------------------------------------------------------------------

    /**
     * Common POST pipeline: guest → CSRF → rate limit → action.
     *
     * @param positive-int $limit per-minute hits
     */
    private function post(string $scope, int $limit, Closure $work): Response
    {
        $guest = $this->guestService()->resolve($this->request);

        return $this->wrap(function (array $graph) use ($guest, $scope, $limit, $work): Response {
            $this->guests->assertCsrf($this->request, $guest);
            $graph['limiter']->assertLimit((int) $guest['id'], $scope, $limit, 60, time());

            return $work($graph, $guest);
        });
    }

    /** Domain errors → JSON envelope; unexpected errors surface as HTTP 500. */
    private function wrap(Closure $work): Response
    {
        try {
            return $work($this->graph());
        } catch (KalaqparException $error) {
            return $this->fail($error);
        }
    }

    private function fail(KalaqparException $error): Response
    {
        return Response::json($error->body(), HttpStatusEnum::from($error->httpStatus()));
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        $data = $this->request->getData();

        return is_array($data) ? $data : [];
    }

    private function requiredRoom(): string
    {
        $code = strtoupper(trim((string) ($this->body()['room'] ?? '')));

        if ($code === '') {
            throw new KalaqparException(KalaqparException::VALIDATION, 'کد اتاق مشخص نشده است.', 400);
        }

        return $code;
    }

    /**
     * Lazily-built service graph, shared across the request.
     *
     * @return array{engine: GameEngine, state: GameState, limiter: RateLimiter,
     *               rooms: \Src\Modules\Kalaqpar\Repositories\RoomRepository,
     *               guests: \Src\Modules\Kalaqpar\Repositories\GuestRepository,
     *               connection: \Src\Modules\Kalaqpar\Database\Connection,
     *               options: \Src\Modules\Kalaqpar\Services\GameOptions}
     */
    private function graph(): array
    {
        if ($this->graph === null) {
            $factory = new KalaqparFactory();
            $this->graph = $factory->services($this->modulePath());
        }

        return $this->graph;
    }

    private function guestService(): GuestService
    {
        if ($this->guests === null) {
            $this->guests = (new KalaqparFactory())
                ->guestService($this->graph()['connection'], $this->graph()['guests']);
        }

        return $this->guests;
    }

    private function modulePath(): string
    {
        return dirname(__DIR__, 1);
    }
}
