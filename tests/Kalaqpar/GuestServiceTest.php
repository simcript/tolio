<?php

namespace Tests\Kalaqpar;

use Pano\Foundation\Request;
use Src\Modules\Kalaqpar\Exceptions\KalaqparException;
use Src\Modules\Kalaqpar\Services\GuestService;

/**
 * Guest identity + CSRF behavior without touching any HTTP headers: the
 * service is exercised the way pages and the API call it, with the cookie
 * writer injected (the CLI never emits record headers).
 */
final class GuestServiceTest extends KalaqparTestCase
{
    private function request(string $uri = '/kalaqpar/', array $data = []): Request
    {
        $_SERVER = ['REQUEST_METHOD' => empty($data) ? 'GET' : 'POST', 'REQUEST_URI' => $uri, 'HTTP_HOST' => 'tools.test'];

        return new Request($_SERVER);
    }

    public function test_resolve_creates_a_guest_and_issues_a_cookie_once(): void
    {
        $request = $this->request();

        $guest = $this->guestService->resolve($request);

        self::assertNotEmpty($guest['id']);
        self::assertCount(1, $this->issuedCookies, 'Exactly one cookie write on first visit.');

        [$token, $expires] = $this->issuedCookies[0];
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        self::assertGreaterThanOrEqual(time() + GuestService::COOKIE_TTL - 5, $expires,
            'The cookie lives for the configured guest TTL.');

        $row = $this->guests->byTokenHash(hash('sha256', $token));
        self::assertNotNull($row, 'The token is stored hashed and resolvable.');
        self::assertSame((int) $guest['id'], (int) $row['id']);
    }

    public function test_resolve_replays_the_cookie_to_the_same_guest(): void
    {
        $guest = $this->guestService->resolve($this->request());
        $token = $this->issuedCookies[0][0];

        // Simulate the next HTTP request carrying the module cookie.
        $_COOKIE[GuestService::COOKIE] = $token;

        $replayed = $this->guestService->resolve($this->request());

        self::assertSame((int) $guest['id'], (int) $replayed['id']);
        self::assertCount(1, $this->issuedCookies, 'No extra cookie is written for a known guest.');
    }

    public function test_csrf_accepts_the_issued_token_and_rejects_forgery(): void
    {
        $request = $this->request();
        $guest = $this->guestService->resolve($request);

        // CORS-style full path: forged token must not pass.
        try {
            $forged = $this->request('/kalaqpar/guess');
            $forged->attributes->set('kalaqpar.csrf', 'not-the-token');
            $this->guestService->assertCsrf($forged, $guest);
            self::fail('A forged CSRF token must be rejected.');
        } catch (KalaqparException $error) {
            self::assertSame(KalaqparException::CSRF, $error->error);
        }

        // The genuine token (attribute fallback; CLI requests carry no headers).
        $request->attributes->set('kalaqpar.csrf', (string) $guest['csrf_token']);
        $this->guestService->assertCsrf($request, $guest);
        static::assertTrue(true, 'assertCsrf is void and passes silently for the issued token.');

        $this->guestService->rotateCsrf((int) $guest['id']);

        // The comparison always runs against a freshly-resolved row (as the
        // API does at request start): the old token must no longer match.
        $fresh = $this->guests->byId((int) $guest['id']);
        self::assertNotNull($fresh);

        try {
            $this->guestService->assertCsrf($request, $fresh);
            self::fail('Rotated token invalidates the previous one.');
        } catch (KalaqparException $error) {
            self::assertSame(KalaqparException::CSRF, $error->error);
        }
    }

    public function test_rename_validates_persian_names(): void
    {
        $guest = $this->guestService->resolve($this->request());

        try {
            $this->guestService->rename($guest, 'ی');
            self::fail('Name below the length limit must be rejected.');
        } catch (KalaqparException $error) {
            self::assertSame(KalaqparException::VALIDATION, $error->error);
        }

        $renamed = $this->guestService->rename($guest, ' کلاغ‌بنو ');
        self::assertSame('کلاغ‌بنو', $renamed, 'Names keep internal ZWNJ but outer whitespace is trimmed.');

        $row = $this->guests->byId((int) $guest['id']);
        self::assertSame('کلاغ‌بنو', $row['display_name']);
    }
}
