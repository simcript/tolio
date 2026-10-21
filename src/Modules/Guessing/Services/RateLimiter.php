<?php

namespace Src\Modules\Guessing\Services;

use Src\Modules\Guessing\Database\Connection;
use Src\Modules\Guessing\Exceptions\GuessingException;

/**
 * Fixed-window rate limiting scoped to the guessing_rate_limits table. The
 * windows are per guest + scope, so pressure on one endpoint (creating
 * rooms, hammering the poll endpoint) can never block a different one.
 */
final class RateLimiter
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Records one hit inside the window. Throws a rate_limited domain error
     * (HTTP 429) once $limit is exceeded.
     */
    public function assertLimit(int $guestId, string $scope, int $limit, int $windowSeconds, int $now): void
    {
        $windowStart = $now - ($now % $windowSeconds);

        $this->db->transaction(function () use ($guestId, $scope, $limit, $windowSeconds, $windowStart): void {
            $row = $this->db->one(
                'SELECT window_started_at, hits FROM guessing_rate_limits
                 WHERE guest_id = ? AND scope = ?',
                [$guestId, $scope]
            );

            $hits = 0;

            if ($row !== null && (int) $row['window_started_at'] === $windowStart) {
                $hits = (int) $row['hits'];
            }

            if ($hits >= $limit) {
                $retryAfter = $windowStart + $windowSeconds - time();

                throw new GuessingException(
                    GuessingException::RATE_LIMITED,
                    'کمی آرام‌تر! چند لحظه بعد دوباره تلاش کن.',
                    429,
                    ['retry_after' => max(1, $retryAfter), 'scope' => $scope],
                );
            }

            $this->db->run(
                'INSERT INTO guessing_rate_limits (guest_id, scope, window_started_at, hits)
                 VALUES (?, ?, ?, 1)
                 ON CONFLICT(guest_id, scope) DO UPDATE SET
                    window_started_at = excluded.window_started_at,
                    hits = CASE
                        WHEN guessing_rate_limits.window_started_at = excluded.window_started_at
                        THEN guessing_rate_limits.hits + 1
                        ELSE 1
                    END',
                [$guestId, $scope, $windowStart]
            );
        });
    }
}
