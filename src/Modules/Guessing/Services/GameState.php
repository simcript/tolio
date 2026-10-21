<?php

namespace Src\Modules\Guessing\Services;

use Src\Modules\Guessing\Database\Connection;
use Src\Modules\Guessing\Repositories\RoundRepository;
use Src\Modules\Guessing\Repositories\RoomRepository;

/**
 * Builds the room state document behind GET /guessing/state. The secret
 * word and unrevealed letters never leave the server: clients only receive
 * already-revealed letters and the word becomes public through the
 * round_finished event once the round is over.
 */
final class GameState
{
    private const MAX_EVENTS = 100;

    public function __construct(
        private readonly Connection $db,
        private readonly RoomRepository $rooms,
        private readonly RoundRepository $rounds,
        private readonly GameOptions $options,
    ) {
    }

    /**
     * Full state of one room for the given viewer.
     *
     * @return array{ok: bool, room: array<string, mixed>, round: null|array<string, mixed>,
     *               me: null|array<string, mixed>, events: list<array<string, mixed>>, truncated: bool}
     */
    public function forRoom(string $code, ?array $guest, int $afterVersion, ?int $now = null): array
    {
        $room = $this->rooms->byCode($code);

        if ($room === null) {
            return [
                'ok' => false,
                'room' => [],
                'round' => null,
                'me' => null,
                'players' => [],
                'scores' => [],
                'events' => [],
                'truncated' => false,
            ];
        }

        $roomId = (int) $room['id'];
        $members = $this->rooms->members($roomId);
        $isMember = $guest !== null && $this->rooms->isMember($roomId, (int) $guest['id']);

        $players = [];
        foreach ($members as $index => $member) {
            $players[] = [
                'guest_id' => (int) $member['guest_id'],
                'display_name' => (string) $member['display_name'],
                'online' => ($now ?? time()) - (int) $member['active_at'] <= $this->options->heartbeatTimeout,
            ];
        }

        $round = null;
        if ($isMember) {
            $round = $this->roundState($roomId, (int) $guest['id'], ($now ?? time()));
        }

        $events = [];
        $truncated = false;

        if ($afterVersion < (int) $room['state_version']) {
            $events = $this->db->all(
                'SELECT version, type, payload FROM guessing_events
                 WHERE room_id = ? AND version > ? ORDER BY version ASC LIMIT ?',
                [$roomId, $afterVersion, self::MAX_EVENTS + 1]
            );

            if (count($events) > self::MAX_EVENTS) {
                array_pop($events);
                $truncated = true;
            }

            $events = array_map(static function (array $event): array {
                $decoded = json_decode((string) $event['payload'], true);

                return [
                    'version' => (int) $event['version'],
                    'type' => (string) $event['type'],
                    'payload' => is_array($decoded) ? $decoded : [],
                ];
            }, $events);
        }

        return [
            'ok' => true,
            'room' => [
                'code' => (string) $room['code'],
                'name' => (string) $room['name'],
                'status' => (string) $room['status'],
                'rounds_total' => (int) $room['rounds_total'],
                'round_number' => (int) $room['current_round_number'],
                'host_guest_id' => (int) $room['host_guest_id'],
                'version' => (int) $room['state_version'],
            ],
            'round' => $round,
            'me' => $guest === null ? null : [
                'guest_id' => (int) $guest['id'],
                'display_name' => (string) $guest['display_name'],
                'is_member' => $isMember,
                'is_host' => (int) $room['host_guest_id'] === (int) $guest['id'],
            ],
            'players' => $players,
            'scores' => $this->roomScores($roomId),
            'events' => $events,
            'truncated' => $truncated,
        ];
    }

    /**
     * Room scoreboard derived from the insert-only ledger; totals are never
     * denormalized so they can only change with ledger rows.
     *
     * @return list<array{guest_id: int, display_name: string, total: int, wins: int}>
     */
    private function roomScores(int $roomId): array
    {
        $rows = $this->db->all(
            'SELECT g.id AS guest_id, g.display_name,
                    COALESCE(SUM(s.points), 0) AS total,
                    COUNT(*) AS wins
             FROM guessing_scores s
             JOIN guessing_guests g ON g.id = s.guest_id
             WHERE s.room_id = ?
             GROUP BY g.id, g.display_name
             ORDER BY total DESC, wins DESC
             LIMIT 50',
            [$roomId]
        );

        return array_map(static fn (array $row): array => [
            'guest_id' => (int) $row['guest_id'],
            'display_name' => (string) $row['display_name'],
            'total' => (int) $row['total'],
            'wins' => (int) $row['wins'],
        ], $rows);
    }

    /**
     * Visible round description for one member: reveal progress, my guess
     * budget, and my personal cooldown remaining.
     *
     * @return null|array<string, mixed>
     */
    private function roundState(int $roomId, int $guestId, int $now): ?array
    {
        $round = $this->rounds->runningRound($roomId);

        if ($round === null) {
            return null;
        }

        $letters = mb_str_split(str_replace("\u{200C}", '', (string) $round['word']), 1, 'UTF-8');

        $participant = $this->db->one(
            'SELECT wrong_guesses, last_guess_at FROM guessing_round_players WHERE round_id = ? AND guest_id = ?',
            [(int) $round['id'], $guestId]
        );

        $wrongGuesses = (int) ($participant['wrong_guesses'] ?? 0);
        $lastGuessAt = (int) ($participant['last_guess_at'] ?? 0);

        return [
            'id' => (int) $round['id'],
            'round_number' => (int) $round['round_number'],
            'status' => (string) $round['status'],
            'word_length' => count($letters),
            'revealed' => array_slice($letters, 0, (int) $round['revealed_count']),
            'revealed_count' => (int) $round['revealed_count'],
            'started_at' => (int) $round['started_at'],
            'next_reveal_at' => (int) $round['next_reveal_at'],
            'ends_at' => (int) $round['ends_at'],
            'wrong_guesses' => $wrongGuesses,
            'guesses_left' => max(0, $this->options->wrongGuessLimit - $wrongGuesses),
            'cooldown_left' => max(0, $this->options->guessCooldown - ($now - $lastGuessAt)),
        ];
    }
}
