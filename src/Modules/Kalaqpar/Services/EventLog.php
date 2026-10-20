<?php

namespace Src\Modules\Kalaqpar\Services;

use Src\Modules\Kalaqpar\Database\Connection;

/**
 * Room event feed. Each mutation bumps the room's state_version inside the
 * same transaction that inserts the event row, so a client polling with
 * `after=version` can never observe a room transition twice or miss one.
 */
final class EventLog
{
    public const ROUND_STARTED = 'round_started';
    public const LETTER_REVEALED = 'letter_revealed';
    public const ROUND_FINISHED = 'round_finished';
    public const PLAYER_JOINED = 'player_joined';
    public const PLAYER_LEFT = 'player_left';
    public const ROOM_CLOSED = 'room_closed';
    public const GAME_STARTED = 'game_started';

    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Bumps the room's state_version and stores the event atomically.
     * Must be called inside the caller's transaction.
     *
     * @param array<string, mixed> $payload
     * @return int the new room version
     */
    public function publish(int $roomId, string $type, array $payload, int $now): int
    {
        $this->db->run(
            'UPDATE kalaqpar_rooms SET state_version = state_version + 1, updated_at = ? WHERE id = ?',
            [$now, $roomId]
        );

        $version = (int) $this->db->run(
            'SELECT state_version FROM kalaqpar_rooms WHERE id = ?',
            [$roomId]
        )->fetchColumn();

        $this->db->run(
            'INSERT INTO kalaqpar_events (room_id, version, type, payload, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$roomId, $version, $type, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $now]
        );

        return $version;
    }

    /**
     * Events of a room newer than $afterVersion. Empty list means "you are
     * already current".
     *
     * @return list<array<string, mixed>>
     */
    public function since(int $roomId, int $afterVersion, int $limit = 100): array
    {
        return $this->db->all(
            'SELECT version, type, payload FROM kalaqpar_events
             WHERE room_id = ? AND version > ?
             ORDER BY version ASC
             LIMIT ?',
            [$roomId, $afterVersion, $limit]
        );
    }
}
