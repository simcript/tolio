<?php

namespace Src\Modules\Guessing\Handlers;

use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;
use Pano\Kernel\HttpStatusEnum;
use Src\Modules\Guessing\Exceptions\GuessingException;
use Src\Modules\Guessing\GuessingFactory;
use Src\Modules\Guessing\Services\EventLog;
use Src\Modules\Guessing\Services\GameEngine;
use Src\Modules\Guessing\Services\GameState;
use Src\Modules\Guessing\Services\GuestService;

/**
 * Persian, RTL pages of the module under a module-scoped layout. Page loads
 * boot the guest identity (cookie + CSRF rotation) and — as a lazy
 * fallback — pulse the engine so a fresh page never shows a stale game
 * even when the persistent worker barely runs.
 */
final class LobbyHandler extends BaseHandler
{
    private ?array $graph = null;

    private ?GuestService $guests = null;

    /** Landing page: open rooms, create/join, profile, leaderboard. */
    public function home(): Response
    {
        $graph = $this->graph();
        $guest = $this->guestService()->resolve($this->request);

        $this->lazyTick($graph);

        $rooms = $graph['rooms']->openRooms();
        $csrf = $this->guestService()->rotateCsrf((int) $guest['id']);

        return $this->render('home', [
            'csrf' => $csrf,
            'me' => $guest,
            'rooms' => $rooms,
            'leaderboard' => $graph['guests']->leaderboard(10),
        ]);
    }

    /** Game page for one room; visiting joins the member list once. */
    public function room(string $code): Response
    {
        $graph = $this->graph();
        $guest = $this->guestService()->resolve($this->request);
        $code = strtoupper(trim($code));
        $now = time();

        $room = $graph['rooms']->byCode($code);

        if ($room === null) {
            return Response::html(
                $this->module->view()->with([
                    'message' => 'این اتاق وجود ندارد؛ احتمالاً کد را اشتباه وارد کردی.',
                ])->layout('layout')->render('error'),
                HttpStatusEnum::NOT_FOUND
            );
        }

        $this->lazyTick($graph);

        $roomId = (int) $room['id'];
        $justJoined = false;

        if ($room['status'] !== 'closed' && !$graph['rooms']->isMember($roomId, (int) $guest['id'])) {
            $graph['connection']->transaction(function () use ($graph, $guest, $roomId, $now): void {
                $graph['rooms']->join($roomId, (int) $guest['id'], $now);
                $graph['events']->publish($roomId, EventLog::PLAYER_JOINED, [
                    'guest_id' => (int) $guest['id'],
                    'display_name' => (string) $guest['display_name'],
                    'via_page' => true,
                ], $now);
            });
            $justJoined = true;
        }

        $graph['rooms']->markActive($roomId, $now);

        $state = $graph['state']->forRoom($code, $guest, 0, $now);
        $csrf = $this->guestService()->rotateCsrf((int) $guest['id']);

        return $this->render('room', [
            'csrf' => $csrf,
            'me' => $guest,
            'room' => $room,
            'state' => $state,
            'justJoined' => $justJoined,
        ]);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * The page pulse flows through the same engine the CLI worker uses;
     * SQLite transactions serialize any overlap between the two.
     */
    private function lazyTick(array $graph): void
    {
        try {
            /** @var GameEngine $engine */
            $engine = $graph['engine'];
            $engine->tick(time());
        } catch (\Throwable $error) {
            $this->module->log()->error('Guessing lazy tick failed: ' . $error->getMessage());
        }
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): Response
    {
        return Response::html(
            $this->module->view()
                ->with($data)
                ->layout('layout')
                ->render($view)
        );
    }

    /**
     * Lazily-built service graph, shared across the request.
     *
     * @return array{connection: \Src\Modules\Guessing\Database\Connection,
     *               rooms: \Src\Modules\Guessing\Repositories\RoomRepository,
     *               guests: \Src\Modules\Guessing\Repositories\GuestRepository,
     *               events: EventLog, engine: GameEngine, state: GameState}
     */
    private function graph(): array
    {
        if ($this->graph === null) {
            $factory = new GuessingFactory();
            $this->graph = $factory->services(dirname(__DIR__, 1));
        }

        return $this->graph;
    }

    private function guestService(): GuestService
    {
        if ($this->guests === null) {
            $graph = $this->graph();
            $this->guests = (new GuessingFactory())->guestService($graph['connection'], $graph['guests']);
        }

        return $this->guests;
    }
}
