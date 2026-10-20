<?php

namespace Tests\Kalaqpar;

use Pano\Foundation\Request;
use Src\Modules\Kalaqpar\Exceptions\KalaqparException;
use Src\Modules\Kalaqpar\Handlers\ApiHandler;
use Src\Modules\Kalaqpar\KalaqparModule;
use Src\Modules\Kalaqpar\Services\GuestService;
use Src\Foundation\DefaultFoundation;

/**
 * HTTP-level flows of the module API: guest bootstrapping, CSRF, rate
 * limits, and the room lifecycle — against the isolated test database and
 * without a live web server (Pano handlers are plain classes).
 *
 * Guest identity is cookie-driven; tests simulate cookies through the
 * $_COOKIE superglobal exactly like the framework reads it.
 */
final class ApiHandlerTest extends KalaqparTestCase
{
    private function request(string $method, string $uri): Request
    {
        $_SERVER = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'QUERY_STRING' => parse_url($uri, PHP_URL_QUERY) ?? '',
            'HTTP_HOST' => 'tools.test',
        ];
        $_POST = [];

        return new Request($_SERVER);
    }

    private function withJsonBody(Request $request, array $data): Request
    {
        $property = new \ReflectionProperty($request, 'data');
        $property->setAccessible(true);
        $property->setValue($request, $data);

        return $request;
    }

    /**
     * Invokes a handler action and returns [body, status code].
     *
     * @return array{0: string, 1: int}
     */
    private function respond(ApiHandler $handler, string $action): array
    {
        $response = $handler->{$action}();

        $get = static function (string $property) use ($response) {
            $reflection = new \ReflectionProperty(\Pano\Kernel\BaseResponse::class, $property);
            $reflection->setAccessible(true);
            return $reflection->getValue($response);
        };

        $body = (string) $get('body');
        $status = $get('status');
        $code = $status instanceof \Pano\Kernel\HttpStatusEnum ? $status->value : 0;

        return [$body, $code];
    }

    private function handlerFor(Request $request): ApiHandler
    {
        return new ApiHandler($request, new KalaqparModule($request, new DefaultFoundation()));
    }

    private function withGuestCookie(string $key): void
    {
        $_COOKIE[GuestService::COOKIE] = self::cookieTokenFor($key);
    }

    public function test_full_trip_create_join_start_and_state(): void
    {
        // 1. Host visits the page: identity persists via the module cookie.
        $this->withGuestCookie('guest-A');
        $hostRequest = $this->request('GET', '/kalaqpar/');
        $host = $this->guestService->resolve($hostRequest);
        self::assertCount(1, $this->issuedCookies);

        // The issued cookie is what the browser replays — the raw bootstrap
        // cookie maps to no stored row and would mint a second guest.
        $issuedCookie = (string) $this->issuedCookies[0][0];
        $_COOKIE[GuestService::COOKIE] = $issuedCookie;

        // 2. Host creates a room; identity replays + CSRF via the shim.
        $hostHandler = $this->handlerFor($this->withJsonBody(
            $this->withCsrf($this->request('POST', '/kalaqpar/rooms'), (string) $host['csrf_token']),
            ['name' => 'اتاق تست', 'rounds' => 3]
        ));

        [$body, $status] = $this->respond($hostHandler, 'createRoom');
        $created = json_decode($body, true);

        self::assertSame(201, $status);
        self::assertTrue($created['ok']);
        self::assertSame(6, strlen($created['room']));

        $code = $created['room'];

        // 3. Player joins with their own persistent cookie.
        $player = $this->guest('guest-B', 'هم‌بازی');
        $this->withGuestCookie('guest-B');

        $playerHandler = $this->handlerFor($this->withJsonBody(
            $this->withCsrf($this->request('POST', '/kalaqpar/rooms/join'), (string) $player['csrf_token']),
            ['room' => $code]
        ));
        [$body, $status] = $this->respond($playerHandler, 'joinRoom');

        self::assertSame(200, $status);
        self::assertSame($code, json_decode($body, true)['room']);

        // 4. Host starts the game (same replayed cookie).
        $_COOKIE[GuestService::COOKIE] = $issuedCookie;
        $hostHandler = $this->handlerFor($this->withJsonBody(
            $this->withCsrf($this->request('POST', '/kalaqpar/rooms/start'), (string) $host['csrf_token']),
            ['room' => $code]
        ));
        [$body, $status] = $this->respond($hostHandler, 'startGame');

        self::assertSame(200, $status);

        // 5. State reflects a running first round without leaking the word.
        $stateHandler = $this->handlerFor($this->request('GET', '/kalaqpar/state?room=' . $code));
        [$body, $status] = $this->respond($stateHandler, 'state');
        $state = json_decode($body, true);

        self::assertSame(200, $status);
        self::assertTrue($state['ok']);
        self::assertSame('playing', $state['room']['status']);
        self::assertCount(2, $state['players']);
        self::assertNotNull($state['round']);
        self::assertArrayNotHasKey('word', $state['round']);
        self::assertSame(0, $state['round']['revealed_count']);
    }

    private function withCsrf(Request $request, string $token): Request
    {
        // CLI requests can never carry HTTP headers, so the mirrored token
        // rides the request attributes (documented GuestService fallback).
        $request->attributes->set('kalaqpar.csrf', $token);

        return $request;
    }

    public function test_state_requires_a_room_code(): void
    {
        $this->guestService->resolve($this->request('GET', '/kalaqpar/'));
        $this->withGuestCookie('guest-A');

        $handler = $this->handlerFor($this->request('GET', '/kalaqpar/state'));
        [$body, $status] = $this->respond($handler, 'state');

        self::assertSame(400, $status);
        self::assertSame(KalaqparException::VALIDATION, json_decode($body, true)['error']);
    }

    public function test_state_for_unknown_room_reports_ok_false(): void
    {
        $this->guestService->resolve($this->withGuestCookieRequest());
        $this->withGuestCookie('guest-A');

        $handler = $this->handlerFor($this->request('GET', '/kalaqpar/state?room=NOPE12'));
        [$body, $status] = $this->respond($handler, 'state');
        $state = json_decode($body, true);

        self::assertSame(200, $status);
        self::assertFalse($state['ok']);
    }

    public function test_buttons_reject_forged_csrf(): void
    {
        $this->guestService->resolve($this->withGuestCookieRequest());
        $this->withGuestCookie('guest-A');

        $handler = $this->handlerFor($this->withCsrf(
            $this->withJsonBody($this->request('POST', '/kalaqpar/rooms'), []),
            'forged-token'
        ));

        [$body, $status] = $this->respond($handler, 'createRoom');

        self::assertSame(403, $status);
        self::assertSame(KalaqparException::CSRF, json_decode($body, true)['error']);
    }

    public function test_fresh_guest_gets_identity_but_is_still_csrf_gated(): void
    {
        // A POST with no cookie at all boots a fresh guest identity but
        // POSTs must always pass the CSRF check — the UI obtains the token
        // from a page visit first.
        unset($_COOKIE[GuestService::COOKIE]);
        $request = $this->withJsonBody($this->request('POST', '/kalaqpar/rooms'), ['name' => 'بدون بازیکن']);

        $handler = $this->handlerFor($request);
        [$body, $status] = $this->respond($handler, 'createRoom');

        self::assertSame(403, $status, 'No CSRF token, no mutation.');
        self::assertSame(KalaqparException::CSRF, json_decode($body, true)['error']);
        self::assertSame(
            1,
            (int) $this->connection->run('SELECT COUNT(*) FROM kalaqpar_guests')->fetchColumn(),
            'The fresh identity was persisted (cookie issuance is covered by GuestServiceTest).'
        );
    }

    public function test_compounding_rooms_hits_are_rate_limited(): void
    {
        $guest = $this->guest('guest-A', 'سازنده');
        $this->withGuestCookie('guest-A');

        for ($index = 1; $index <= self::CREATE_LIMIT_MIN; $index++) {
            $request = $this->withCsrf(
                $this->withJsonBody($this->request('POST', '/kalaqpar/rooms'), []),
                (string) $guest['csrf_token']
            );
            [$body, $status] = $this->respond($this->handlerFor($request), 'createRoom');

            self::assertSame(201, $status, "Creation #$index must pass inside the window.");
        }

        $request = $this->withCsrf(
            $this->withJsonBody($this->request('POST', '/kalaqpar/rooms'), []),
            (string) $guest['csrf_token']
        );
        [$body, $status] = $this->respond($this->handlerFor($request), 'createRoom');

        self::assertSame(429, $status);
        self::assertSame(KalaqparException::RATE_LIMITED, json_decode($body, true)['error']);
    }

    public function test_leaderboard_endpoint_returns_array_payload(): void
    {
        $this->guestService->resolve($this->withGuestCookieRequest());

        $handler = $this->handlerFor($this->request('GET', '/kalaqpar/leaderboard'));
        [$body, $status] = $this->respond($handler, 'leaderboard');
        $data = json_decode($body, true);

        self::assertSame(200, $status);
        self::assertTrue($data['ok']);
        self::assertIsArray($data['leaderboard']);
    }

    private const CREATE_LIMIT_MIN = ApiHandler::CREATE_LIMIT_MIN;

    private function withGuestCookieRequest(): Request
    {
        $_COOKIE[GuestService::COOKIE] = 'guest-A';

        return $this->request('GET', '/kalaqpar/');
    }
}
