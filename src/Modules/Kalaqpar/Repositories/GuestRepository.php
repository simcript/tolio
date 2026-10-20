<?php

namespace Src\Modules\Kalaqpar\Repositories;

use Src\Modules\Kalaqpar\Database\Connection;

/**
 * Guest identity persistence. Tokens are only ever stored as SHA-256
 * hashes, so a database leak cannot be replayed against the cookie.
 */
final class GuestRepository
{
    private const COOKIE_PREFIX = 'کاربر-';

    public function __construct(private readonly Connection $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function byId(int $guestId): ?array
    {
        return $this->db->one(
            'SELECT * FROM kalaqpar_guests WHERE id = ?',
            [$guestId]
        );
    }

    /** @return array<string, mixed>|null */
    public function byTokenHash(string $tokenHash): ?array
    {
        return $this->db->one(
            'SELECT * FROM kalaqpar_guests WHERE token_hash = ?',
            [$tokenHash]
        );
    }

    /**
     * Creates a fresh guest with a random display name seed; the player can
     * rename themselves later through the profile endpoint.
     *
     * @return array{name: string, id: int}
     */
    public function create(string $tokenHash): array
    {
        $name = self::COOKIE_PREFIX . random_int(1000, 9999);

        $this->db->run(
            'INSERT INTO kalaqpar_guests (token_hash, csrf_token, display_name, created_at, last_seen_at)
             VALUES (?, ?, ?, ?, ?)',
            [$tokenHash, self::randomCsrf(), $name, time(), time()]
        );

        return [
            'id' => (int) $this->db->pdo()->lastInsertId(),
            'name' => $name,
        ];
    }

    public function touch(int $guestId): void
    {
        $this->db->run(
            'UPDATE kalaqpar_guests SET last_seen_at = ? WHERE id = ?',
            [time(), $guestId]
        );
    }

    public function rotateCsrf(int $guestId, string $csrfToken): void
    {
        $this->db->run(
            'UPDATE kalaqpar_guests SET csrf_token = ? WHERE id = ?',
            [$csrfToken, $guestId]
        );
    }

    /** @return list<array{guest_id: int, display_name: string, total: int, wins: int}> */
    public function leaderboard(int $limit = 10): array
    {
        $rows = $this->db->all(
            'SELECT g.id AS guest_id, g.display_name,
                    COALESCE(SUM(s.points), 0) AS total,
                    COUNT(s.points) AS wins
             FROM kalaqpar_guests g
             LEFT JOIN kalaqpar_scores s ON s.guest_id = g.id
             GROUP BY g.id, g.display_name
             HAVING total > 0
             ORDER BY total DESC, wins DESC, g.id ASC
             LIMIT ?',
            [$limit]
        );

        return array_map(static fn (array $row): array => [
            'guest_id' => (int) $row['guest_id'],
            'display_name' => (string) $row['display_name'],
            'total' => (int) $row['total'],
            'wins' => (int) $row['wins'],
        ], $rows);
    }

    private static function randomCsrf(): string
    {
        return bin2hex(random_bytes(20));
    }
}
