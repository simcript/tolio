<?php
namespace Src\Modules\Kalaqpar\Services;

use PDOException;
use Src\Modules\Kalaqpar\Database\Connection;

final class Invitation
{
    public function __construct(private Connection $db, private array $settings) {}

    public static function normalize(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 96) { throw self::malformed(); }
        $value = strtr($value, array_combine(
            ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'],
            str_split('01234567890123456789')
        ));
        // Only ordinary spaces and a single optional middle hyphen are allowed.
        $value = str_replace(' ', '', $value);
        if (!preg_match('/^[1-9][0-9]{2}-?[0-9]{3}$/D', $value)) { throw self::malformed(); }
        return str_replace('-', '', $value);
    }

    private static function malformed(): GameError
    {
        return new GameError('invalid_code', 'کد شش‌رقمی اتاق را درست وارد کن.', 422);
    }

    public static function unavailable(): GameError
    {
        return new GameError('invalid_invitation', 'این دعوت معتبر نیست یا بسته و منقضی شده؛ از دوستت دعوت تازه بگیر.', 404);
    }

    // BEGIN IMMEDIATE and the UNIQUE constraint arbitrate collisions,
    // including with closed rooms: issued codes are reserved permanently.
    public static function allocate(Connection $db, callable $store): string
    {
        return $db->atomic(static function () use ($store) {
            for ($attempt = 0; $attempt < 32; $attempt++) {
                $code = (string) random_int(100000, 999999);
                try { $store($code); return $code; }
                catch (PDOException $e) {
                    if (((int) ($e->errorInfo[1] ?? 0) & 255) !== 19 || !str_contains($e->getMessage(), 'UNIQUE constraint failed: kalaqpar_rooms.invitation')) { throw $e; }
                }
            }
            throw new GameError('codes_busy', 'ساخت کد اتاق فعلاً ممکن نیست؛ کمی بعد دوباره تلاش کن.', 503);
        });
    }

    public function resolve(mixed $value, ?int $now = null): array
    {
        if (is_string($value) && preg_match('/^[a-f0-9]{48}$/D', $value)) {
            $room = $this->db->one("SELECT * FROM kalaqpar_rooms WHERE legacy_invitation=? AND mode='private'", [$value]);
        } else {
            $code = self::normalize($value);
            $room = $this->db->one("SELECT * FROM kalaqpar_rooms WHERE invitation=? AND mode='private'", [$code]);
        }
        if (!$room || !$this->valid($room, $now)) { throw self::unavailable(); }
        return $room;
    }

    public function valid(array $room, ?int $now = null): bool
    {
        if ($room['mode'] !== 'private' || $room['phase'] === 'closed') { return false; }
        if ($room['phase'] === 'playing' || $room['empty_since'] === null) { return true; }
        $now ??= Settings::now();
        if ($room['empty_since'] + $this->settings['room_ttl_ms'] > $now) { return true; }
        // Match the cleanup rule, even when shared-mode cleanup has not run yet.
        return (bool) $this->db->one('SELECT 1 FROM kalaqpar_engagements e JOIN kalaqpar_users u ON u.id=e.user_id WHERE e.room_id=? AND e.departed=0 AND u.seen_at>? LIMIT 1', [$room['id'], $now - $this->settings['offline_ms']]);
    }

    public function details(array $room): array
    {
        $code = self::normalize($room['invitation']);
        return ['invitation' => $code, 'invitation_code' => $code,
            'invitation_formatted' => substr($code, 0, 3) . '-' . substr($code, 3),
            'invitation_url' => PublicUrl::room($code, $this->settings)];
    }

    public function available(array $room): int
    {
        $count = $this->db->one("SELECT COUNT(*) n FROM kalaqpar_engagements WHERE room_id=? AND kind IN ('lobby','waiting')" . ($room['current_match_id'] ? ' AND priority=1' : ''), [$room['id']])['n'];
        return max(0, $room['capacity'] - $count);
    }

    public function preview(mixed $value): array
    {
        return $this->db->snapshot(function () use ($value) {
            $room = $this->resolve($value);
            // No participant/host information or internal room/match identifiers.
            return ['name' => $room['name'], 'capacity' => $room['capacity'], 'phase' => $room['phase'],
                'available' => $this->available($room)] + $this->details($room);
        });
    }
}
