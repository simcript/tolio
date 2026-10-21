<?php

namespace Src\Modules\Guessing\Repositories;

use Src\Modules\Guessing\Database\Connection;
use Src\Modules\Guessing\Exceptions\GuessingException;
use Src\Modules\Guessing\Services\GameRules;

/**
 * Room lifecycle persistence. Every mutating method must be called from
 * inside a Connection::transaction() so concurrent workers serialize.
 */
final class RoomRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function byCode(string $code): ?array
    {
        return $this->db->one('SELECT * FROM guessing_rooms WHERE code = ?', [strtoupper($code)]);
    }

    /** @return array<string, mixed>|null */
    public function byId(int $roomId): ?array
    {
        return $this->db->one('SELECT * FROM guessing_rooms WHERE id = ?', [$roomId]);
    }

    /**
     * Creates a room owned by $hostGuestId and joins the host as first
     * member. Codes are retried on the unique constraint.
     *
     * @return array<string, mixed>
     */
    public function create(string $name, int $rounds, int $hostGuestId, int $now): array
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = GameRules::newRoomCode();

            try {
                $this->db->run(
                    'INSERT INTO guessing_rooms
                        (code, name, host_guest_id, status, rounds_total, created_at, active_at, updated_at)
                     VALUES (?, ?, ?, "lobby", ?, ?, ?, ?)',
                    [$code, $name, $hostGuestId, $rounds, $now, $now, $now]
                );
            } catch (\PDOException $error) {
                if (str_contains($error->getMessage(), 'UNIQUE constraint failed: guessing_rooms.code')) {
                    continue;
                }
                throw $error;
            }

            $room = $this->byCode($code);
            if ($room !== null) {
                $this->join((int) $room['id'], $hostGuestId, $now);
            }

            return $room ?? [];
        }

        throw new GuessingException(GuessingException::CONFLICT, 'ساخت اتاق را یک‌بار دیگر تلاش کن.', 503);
    }

    /**
     * Open (not closed) rooms, freshest first, for the lobby page.
     *
     * @return list<array<string, mixed>>
     */
    public function openRooms(int $limit = 20): array
    {
        return $this->db->all(
            'SELECT r.*, COUNT(rp.guest_id) AS player_count
             FROM guessing_rooms r
             LEFT JOIN guessing_room_players rp ON rp.room_id = r.id
             WHERE r.status IN ("lobby", "playing")
             GROUP BY r.id
             ORDER BY r.active_at DESC
             LIMIT ?',
            [$limit]
        );
    }

    public function join(int $roomId, int $guestId, int $now): void
    {
        $this->db->run(
            'INSERT INTO guessing_room_players (room_id, guest_id, joined_at, active_at)
             VALUES (?, ?, ?, ?)
             ON CONFLICT(room_id, guest_id) DO UPDATE SET active_at = excluded.active_at',
            [$roomId, $guestId, $now, $now]
        );
    }

    public function isMember(int $roomId, int $guestId): bool
    {
        return $this->db->one(
            'SELECT guest_id FROM guessing_room_players WHERE room_id = ? AND guest_id = ?',
            [$roomId, $guestId]
        ) !== null;
    }

    /** @return list<array<string, mixed>> */
    public function members(int $roomId): array
    {
        return $this->db->all(
            'SELECT rp.guest_id, rp.joined_at, rp.active_at, g.display_name
             FROM guessing_room_players rp
             JOIN guessing_guests g ON g.id = rp.guest_id
             WHERE rp.room_id = ?
             ORDER BY rp.joined_at ASC',
            [$roomId]
        );
    }

    public function leave(int $roomId, int $guestId): void
    {
        $this->db->run(
            'DELETE FROM guessing_room_players WHERE room_id = ? AND guest_id = ?',
            [$roomId, $guestId]
        );
    }

    public function memberCount(int $roomId): int
    {
        return (int) $this->db->run(
            'SELECT COUNT(*) FROM guessing_room_players WHERE room_id = ?',
            [$roomId]
        )->fetchColumn();
    }

    /**
     * Host hand-off after the host leaves: the senior remaining member
     * becomes the host. Returns the new host guest id, or null when the
     * room kept no members.
     */
    public function reassignHost(int $roomId): ?int
    {
        $next = $this->db->one(
            'SELECT guest_id FROM guessing_room_players
             WHERE room_id = ? ORDER BY joined_at ASC, guest_id ASC LIMIT 1',
            [$roomId]
        );

        if ($next === null) {
            return null;
        }

        $this->setHost($roomId, (int) $next['guest_id']);

        return (int) $next['guest_id'];
    }

    public function markActive(int $roomId, int $now): void
    {
        $this->db->run(
            'UPDATE guessing_rooms SET active_at = ?, updated_at = ? WHERE id = ?',
            [$now, $now, $roomId]
        );
    }

    /**
     * Bumps the room's state_version monotonically (used together with the
     * event log so clients can resynchronize).
     *
     * @param int|null $activeAt additionally refresh the lobby heartbeat
     */
    public function bumpVersion(int $roomId, int $now, ?int $activeAt = null): int
    {
        $this->db->run(
            'UPDATE guessing_rooms
             SET state_version = state_version + 1, updated_at = ?' .
            ($activeAt !== null ? ', active_at = ?' : '') .
            ' WHERE id = ?',
            $activeAt !== null ? [$now, $activeAt, $roomId] : [$now, $roomId]
        );

        return (int) $this->db->run(
            'SELECT state_version FROM guessing_rooms WHERE id = ?',
            [$roomId]
        )->fetchColumn();
    }

    public function close(int $roomId, string $reason, int $now): void
    {
        $this->db->run(
            'UPDATE guessing_rooms SET status = "closed", closed_at = ?, close_reason = ?, updated_at = ? WHERE id = ?',
            [$now, $reason, $now, $roomId]
        );
    }

    public function setHost(int $roomId, int $guestId): void
    {
        $this->db->run(
            'UPDATE guessing_rooms SET host_guest_id = ? WHERE id = ?',
            [$guestId, $roomId]
        );
    }

    public function enterGame(int $roomId, int $now): void
    {
        $this->db->run(
            'UPDATE guessing_rooms SET status = "playing", updated_at = ? WHERE id = ?',
            [$now, $roomId]
        );
    }

    public function setRoundNumber(int $roomId, int $roundNumber): void
    {
        $this->db->run(
            'UPDATE guessing_rooms SET current_round_number = ? WHERE id = ?',
            [$roundNumber, $roomId]
        );
    }

    /**
     * Lobbies with no activity for the timeout are retired so the open list
     * never fills with abandoned rooms.
     *
     * @return int count of closed rooms
     */
    public function retireIdleLobbies(int $idleBefore, int $now): int
    {
        $stale = $this->db->all(
            'SELECT id FROM guessing_rooms WHERE status = "lobby" AND active_at < ?',
            [$idleBefore]
        );

        foreach ($stale as $room) {
            $this->close((int) $room['id'], 'idle', $now);
        }

        return count($stale);
    }
}
