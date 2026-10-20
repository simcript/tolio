<?php

namespace Src\Modules\Kalaqpar\Services;

use Pano\Kernel\BaseRequest;
use Src\Modules\Kalaqpar\Database\Connection;
use Src\Modules\Kalaqpar\Exceptions\KalaqparException;
use Src\Modules\Kalaqpar\Repositories\GuestRepository;

/**
 * Kalaqpar guest identity — fully module-scoped and separate from the host
 * application (which ships no users or sessions at all).
 *
 * Identity lives in the `kalaqpar_guest` cookie (HttpOnly, SameSite=Lax).
 * Only the SHA-256 hash of the token is stored. Nothing here reads or
 * writes any other cookie, so a signed-in host user's session is never
 * touched even when they play.
 */
final class GuestService
{
    public const COOKIE = 'kalaqpar_guest';
    public const COOKIE_TTL = 31536000; // 365 days
    public const CSRF_HEADER = 'X-Kalaqpar-CSRF';

    private const ATTR_GUEST = 'kalaqpar.guest';
    private const ATTR_COOKIE_ISSUED = 'kalaqpar.cookie_issued';

    /** Cookie writer injected so CLI tests never emit HTTP headers. */
    /** @var callable(string, int):void */
    private $setCookie;

    /**
     * @param null|callable(string, int):void $setCookie
     */
    public function __construct(
        private readonly Connection $db,
        private readonly GuestRepository $guests,
        private readonly int $guestTtlDays = 365,
        ?callable $setCookie = null,
    ) {
        $this->setCookie = $setCookie ?? self::defaultCookieWriter();
    }

    /**
     * Resolves (or creates) the persistent guest for this request. Returns
     * the guest row; when a new token was issued, the cookie is written
     * immediately so later output stays safe.
     *
     * @return array<string, mixed>
     */
    public function resolve(BaseRequest $request): array
    {
        $token = $this->cookieToken();
        $hash = $token === null ? null : hash('sha256', $token);

        $guest = $hash === null ? null : $this->guests->byTokenHash($hash);

        if ($guest === null) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $created = $this->guests->create($hash);

            ($this->setCookie)($token, time() + (86400 * min($this->guestTtlDays, 365)));
            $guest = $this->guests->byId($created['id']);
            $request->attributes->set(self::ATTR_COOKIE_ISSUED, true);
        }

        if ($guest === null) {
            throw new KalaqparException(
                KalaqparException::GUEST_REQUIRED,
                'شناسه بازیکن ساخته نشد.',
                500,
            );
        }

        $request->attributes->set(self::ATTR_GUEST, $guest);
        $this->guests->touch((int) $guest['id']);

        return $guest;
    }

    /** @return array<string, mixed> */
    public function current(BaseRequest $request): array
    {
        $guest = $request->attributes->get(self::ATTR_GUEST);

        if (!is_array($guest)) {
            throw new KalaqparException(
                KalaqparException::GUEST_REQUIRED,
                'برای این درخواست باید بازیکن شناخته شوید.',
                401,
            );
        }

        return $guest;
    }

    /** True when `resolve()` issued a fresh cookie during this request. */
    public function issuedNewCookie(BaseRequest $request): bool
    {
        return (bool) $request->attributes->get(self::ATTR_COOKIE_ISSUED, false);
    }

    /**
     * CSRF for module POSTs: the token rendered into pages must come back
     * in the CSRF header. (The request-attribute fallback keeps the check
     * testable because CLI requests can never populate HTTP headers.)
     */
    public function assertCsrf(BaseRequest $request, array $guest): void
    {
        $headers = $request->getHeaders();
        $token = $headers[strtolower(self::CSRF_HEADER)]
            ?? $headers[self::CSRF_HEADER]
            ?? $request->attributes->get('kalaqpar.csrf');

        if (is_string($token) && hash_equals((string) $guest['csrf_token'], $token)) {
            return;
        }

        throw new KalaqparException(
            KalaqparException::CSRF,
            'درخواست بازی معتبر نبود؛ صفحه را یک‌بار دیگر باز کن.',
            403,
        );
    }

    public function rotateCsrf(int $guestId): string
    {
        $token = bin2hex(random_bytes(20));
        $this->guests->rotateCsrf($guestId, $token);

        return $token;
    }

    /** Validates and stores a display-name change. */
    public function rename(array $guest, string $name): string
    {
        $name = trim($name);

        if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 24) {
            throw new KalaqparException(
                KalaqparException::VALIDATION,
                'نام بازیکن باید ۲ تا ۲۴ حرف باشد.',
            );
        }

        $this->db->run(
            'UPDATE kalaqpar_guests SET display_name = ? WHERE id = ?',
            [$name, (int) $guest['id']]
        );

        return $name;
    }

    private static function cookieToken(): ?string
    {
        $raw = $_COOKIE[self::COOKIE] ?? null;

        if (is_string($raw) && preg_match('/^[0-9a-f]{64}$/', $raw) === 1) {
            return $raw;
        }

        return null;
    }

    private static function defaultCookieWriter(): callable
    {
        return static function (string $token, int $expires): void {
            // CLI/tests never emit HTTP headers; PHPUnit converts them to warnings.
            if (PHP_SAPI === 'cli' || headers_sent()) {
                return;
            }

            $secure = (($_SERVER['REQUEST_SCHEME'] ?? '') === 'https')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

            setcookie(GuestService::COOKIE, $token, [
                'expires' => $expires,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        };
    }
}
