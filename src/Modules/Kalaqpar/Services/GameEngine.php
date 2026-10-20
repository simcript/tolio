<?php

namespace Src\Modules\Kalaqpar\Services;

use Src\Modules\Kalaqpar\Database\Connection;
use Src\Modules\Kalaqpar\Exceptions\KalaqparException;
use Src\Modules\Kalaqpar\Repositories\GuestRepository;
use Src\Modules\Kalaqpar\Repositories\RoundRepository;
use Src\Modules\Kalaqpar\Repositories\RoomRepository;

/**
 * The Kalaqpar game engine. One `tick($now)` advances the whole world:
 * letter reveals, round timeouts, subsequent rounds, room completion and
 * idle-lobby retirement. `tick` is invoked once per worker pulse and once
 * per state read as a lazy fallback, and it is safe under concurrency
 * because every transition re-checks its precondition inside a
 * BEGIN IMMEDIATE transaction (SQLite's single-writer serialization).
 *
 * Game rules live in GameRules (pure); persistence lives in repositories;
 * this class only orchestrates transactions between them. Every entry
 * point receives `now` explicitly, which makes the whole engine
 * deterministic in tests.
 */
final class GameEngine
{
    public function __construct(
        private readonly Connection $db,
        private readonly RoomRepository $rooms,
        private readonly RoundRepository $rounds,
        private readonly EventLog $events,
        private readonly GuestRepository $guests,
        private readonly GameOptions $options,
    ) {
    }

    /**
     * Runs one engine pulse; returns a summary for logs/tests.
     *
     * @return array{reveals: int, finished_rounds: int, started_rounds: int, closed_rooms: int}
     */
    public function tick(int $now): array
    {
        $summary = [
            'reveals' => 0,
            'finished_rounds' => 0,
            'started_rounds' => 0,
            'closed_rooms' => 0,
        ];

        $summary['finished_rounds'] += $this->finishDueRounds($now);
        $summary['reveals'] += $this->revealDueLetters($now);
        $summary['started_rounds'] += $this->startNextRounds($now);
        $summary['closed_rooms'] += $this->retireIdleLobbies($now);

        return $summary;
    }

    // ------------------------------------------------------------------
    // Lifecycle: rooms and membership
    // ------------------------------------------------------------------

    /**
     * Creates a room and joins its creator. Host starts the game later.
     *
     * @param array{name?: string, rounds?: int} $input
     * @return array{code: string, name: string, rounds: int}
     */
    public function createRoom(array $guest, array $input, int $now): array
    {
        $name = trim($input['name'] ?? '');
        if ($name === '' || mb_strlen($name, 'UTF-8') > 40) {
            $name = 'اتاق ' . mb_substr((string) $guest['display_name'], 0, 12, 'UTF-8');
        }

        $rounds = (int) ($input['rounds'] ?? $this->options->defaultRounds);
        $rounds = max(1, min($this->options->maxRounds, $rounds));

        return $this->db->transaction(function () use ($guest, $name, $rounds, $now) {
            $room = $this->rooms->create($name, $rounds, (int) $guest['id'], $now);

            if ($room === []) {
                throw new KalaqparException(KalaqparException::CONFLICT, 'ساخت اتاق ناموفق بود؛ دوباره تلاش کن.', 503);
            }

            $this->events->publish((int) $room['id'], EventLog::PLAYER_JOINED, [
                'guest_id' => (int) $guest['id'],
                'display_name' => (string) $guest['display_name'],
            ], $now);

            return [
                'code' => (string) $room['code'],
                'name' => (string) $room['name'],
                'rounds' => $rounds,
            ];
        });
    }

    public function joinRoom(array $guest, string $code, int $now): void
    {
        $this->db->transaction(function () use ($guest, $code, $now): void {
            $room = $this->requireOpenRoom($code);

            $this->rooms->join((int) $room['id'], (int) $guest['id'], $now);
            $this->rooms->markActive((int) $room['id'], $now);

            $this->events->publish((int) $room['id'], EventLog::PLAYER_JOINED, [
                'guest_id' => (int) $guest['id'],
                'display_name' => (string) $guest['display_name'],
            ], $now);
        });
    }

    public function leaveRoom(array $guest, string $code, int $now): void
    {
        $this->db->transaction(function () use ($guest, $code, $now): void {
            $room = $this->rooms->byCode($code);

            if ($room === null) {
                return; // leaving an unknown room is a tolerated no-op
            }

            $wasHost = (int) $room['host_guest_id'] === (int) $guest['id'];
            $this->rooms->leave((int) $room['id'], (int) $guest['id']);

            $empty = $this->rooms->memberCount((int) $room['id']) === 0;
            if ($empty) {
                $this->rooms->close((int) $room['id'], 'empty', $now);
                $this->events->publish((int) $room['id'], EventLog::ROOM_CLOSED, ['reason' => 'empty'], $now);
                return;
            }

            if ($wasHost) {
                $newHost = $this->rooms->reassignHost((int) $room['id']);
            }

            $this->events->publish((int) $room['id'], EventLog::PLAYER_LEFT, [
                'guest_id' => (int) $guest['id'],
            ], $now);
        });
    }

    /** Host-only: starts the first round. */
    public function startGame(array $guest, string $code, int $now): void
    {
        $this->db->transaction(function () use ($guest, $code, $now): void {
            $room = $this->requireOpenRoom($code);

            if ((int) $room['host_guest_id'] !== (int) $guest['id']) {
                throw new KalaqparException(KalaqparException::NOT_MEMBER, 'فقط میزبان می‌تواند بازی را شروع کند.', 403);
            }

            if ($room['status'] !== 'lobby') {
                throw new KalaqparException(KalaqparException::CONFLICT, 'بازی این اتاق از قبل شروع شده است.', 409);
            }

            $members = $this->rooms->members((int) $room['id']);
            if (count($members) < 2) {
                throw new KalaqparException(KalaqparException::CONFLICT, 'برای شروع بازی حداقل دو بازیکن لازم است.');
            }

            $this->rooms->enterGame((int) $room['id'], $now);
            $this->events->publish((int) $room['id'], EventLog::GAME_STARTED, [], $now);

            $this->startRound($room, $now);
        });
    }

    /**
     * Records a guess. Correct guesses finish the round and append to the
     * score ledger; wrong guesses are budgeted and cooled down per player.
     *
     * @return array{correct: bool, finished: bool}
     */
    public function guess(array $guest, string $code, string $rawGuess, int $now): array
    {
        // A guess after the deadline closes the round in its own committed
        // transaction first: the TOO_LATE error must not roll that state
        // change back.
        $room = $this->rooms->byCode($code);

        if ($room === null) {
            throw new KalaqparException(KalaqparException::NOT_FOUND, 'این اتاق وجود ندارد یا بسته شده است.', 404);
        }

        if (!$this->rooms->isMember((int) $room['id'], (int) $guest['id'])) {
            throw new KalaqparException(KalaqparException::NOT_MEMBER, 'اول به اتاق بپیوند.', 403);
        }

        $round = $this->rounds->runningRound((int) $room['id']);

        if ($round === null) {
            throw new KalaqparException(KalaqparException::TOO_LATE, 'این دور تمام شده؛ منتظر دور بعد بمان.', 409);
        }

        if ($now >= (int) $round['ends_at']) {
            $this->db->transaction(function () use ($room, $round, $now): void {
                $this->rounds->finishTimeout($round, $now);
                $this->events->publish((int) $room['id'], EventLog::ROUND_FINISHED, [
                    'round_number' => (int) $round['round_number'],
                    'reason' => 'timeout',
                    'word' => (string) $round['word'],
                ], $now);
            });

            throw new KalaqparException(KalaqparException::TOO_LATE, 'زمان این دور تمام شد.', 409);
        }

        return $this->db->transaction(function () use ($guest, $room, $round, $rawGuess, $now) {
            $normalized = GameRules::validateGuess($rawGuess);

            if (GameRules::normalize($normalized) === GameRules::normalize((string) $round['word'])) {
                $this->finishRoundSolved($room, $round, $guest, $now);

                return ['correct' => true, 'finished' => true];
            }

            $this->rounds->recordWrongGuess($round, (int) $guest['id'], $normalized, $now,
                $this->options->guessCooldown, $this->options->wrongGuessLimit);

            return ['correct' => false, 'finished' => false];
        });
    }

    // ------------------------------------------------------------------
    // World advancement
    // ------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>> due rounds whose room is live
     */
    private function runningRounds(): array
    {
        return $this->db->all(
            'SELECT ro.* FROM kalaqpar_rounds ro
             JOIN kalaqpar_rooms rm ON rm.id = ro.room_id AND rm.status = "playing"
             WHERE ro.status = "running"'
        );
    }

    /** Letter reveals whose time has come. Returns number of reveals. */
    private function revealDueLetters(int $now): int
    {
        $reveals = 0;

        foreach ($this->runningRounds() as $round) {
            $reveals += $this->db->transaction(function () use ($round, $now): int {
                $fresh = $this->rounds->round((int) $round['id']);

                if ($fresh === null || $fresh['status'] !== 'running') {
                    return 0;
                }

                $result = $this->rounds->maybeReveal($fresh, $now, $this->options->revealSeconds);

                if (!$result['changed']) {
                    return 0;
                }

                $this->events->publish((int) $round['room_id'], EventLog::LETTER_REVEALED, [
                    'round_number' => (int) $round['round_number'],
                    'revealed_count' => count($result['revealed']),
                    'revealed' => $result['revealed'],
                ], $now);

                return 1;
            });
        }

        return $reveals;
    }

    private function finishDueRounds(int $now): int
    {
        $finished = 0;

        foreach ($this->runningRounds() as $round) {
            if ($now < (int) $round['ends_at']) {
                continue;
            }

            $finished += $this->db->transaction(function () use ($round, $now): int {
                $fresh = $this->rounds->round((int) $round['id']);

                if ($fresh === null || $fresh['status'] !== 'running' || $now < (int) $fresh['ends_at']) {
                    return 0;
                }

                $this->rounds->finishTimeout($fresh, $now);
                $this->events->publish((int) $fresh['room_id'], EventLog::ROUND_FINISHED, [
                    'round_number' => (int) $fresh['round_number'],
                    'reason' => 'timeout',
                    'word' => (string) $fresh['word'],
                ], $now);

                return 1;
            });
        }

        return $finished;
    }

    /**
     * Starts the follow-up round of every playing room whose latest round
     * has finished and still has rounds left, and closes rooms whose game
     * completed.
     */
    private function startNextRounds(int $now): int
    {
        $rooms = $this->db->all('SELECT * FROM kalaqpar_rooms WHERE status = "playing"');
        $started = 0;

        foreach ($rooms as $room) {
            $started += $this->db->transaction(function () use ($room, $now): int {
                $latest = $this->rounds->latestRound((int) $room['id']);
                $roomNumber = (int) $room['current_round_number'];

                if ($latest !== null && $latest['status'] === 'running') {
                    return 0;
                }

                if ($roomNumber >= (int) $room['rounds_total']) {
                    $this->rooms->close((int) $room['id'], 'completed', $now);
                    $this->events->publish((int) $room['id'], EventLog::ROOM_CLOSED, ['reason' => 'completed'], $now);

                    return 0;
                }

                if ($latest === null && $roomNumber === 0) {
                    // No first round yet: the host starts the game manually.
                    return 0;
                }

                $this->startRound($room, $now);

                return 1;
            });
        }

        return $started;
    }

    private function retireIdleLobbies(int $now): int
    {
        return $this->db->transaction(function () use ($now): int {
            $idleBefore = $now - $this->options->lobbyTimeout;
            $stale = $this->db->all(
                'SELECT id FROM kalaqpar_rooms WHERE status = "lobby" AND active_at < ?',
                [$idleBefore]
            );

            $this->rooms->retireIdleLobbies($idleBefore, $now);

            $closed = 0;
            foreach ($stale as $room) {
                $this->events->publish((int) $room['id'], EventLog::ROOM_CLOSED, ['reason' => 'idle'], $now);
                $closed++;
            }

            return $closed;
        });
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Starts round_number = current + 1 and snapshots current members.
     * Must be called inside the room's transaction.
     *
     * @param array<string, mixed> $room
     */
    private function startRound(array $room, int $now): array
    {
        $roundNumber = (int) $room['current_round_number'] + 1;
        $members = $this->rooms->members((int) $room['id']);

        $played = $this->db->all(
            'SELECT word FROM kalaqpar_rounds WHERE room_id = ?',
            [(int) $room['id']]
        );

        $round = $this->rounds->startRound(
            (int) $room['id'],
            $roundNumber,
            $this->wordPool(),
            array_map(static fn (array $row): string => (string) $row['word'], $played),
            $members,
            $now,
            $this->options->revealSeconds,
            $this->options->finishGraceSeconds,
            $this->options->roundMaxSeconds,
        );

        $this->rooms->setRoundNumber((int) $room['id'], $roundNumber);
        $this->events->publish((int) $room['id'], EventLog::ROUND_STARTED, [
            'round_number' => $roundNumber,
            'rounds_total' => (int) $room['rounds_total'],
            'word_length' => GameRules::wordLength((string) $round['word']),
            'started_at' => $now,
            'next_reveal_at' => (int) $round['next_reveal_at'],
            'ends_at' => (int) $round['ends_at'],
            'players' => array_map(static fn (array $member): array => [
                'guest_id' => (int) $member['guest_id'],
                'display_name' => (string) $member['display_name'],
            ], $members),
        ], $now);

        return $round;
    }

    /**
     * Scores the winner and closes the round. Must be called inside the
     * room's transaction on an unmodified running round.
     *
     * @param array<string, mixed> $room
     * @param array<string, mixed> $round
     */
    private function finishRoundSolved(array $room, array $round, array $guest, int $now): void
    {
        $this->db->run(
            'UPDATE kalaqpar_rounds
             SET status = "finished", finished_at = ?, finish_reason = "solved", winner_guest_id = ?
             WHERE id = ? AND status = "running"',
            [$now, (int) $guest['id'], (int) $round['id']]
        );

        $base = GameRules::basePoints((int) $round['revealed_count']);
        $streak = $this->rounds->winStreakBefore(
            (int) $room['id'],
            (int) $guest['id'],
            (int) $round['round_number']
        );

        $bonus = GameRules::streakBonus($streak + 1);
        $this->rounds->recordScore(
            (int) $room['id'],
            (int) $round['id'],
            (int) $guest['id'],
            $base,
            $bonus,
            $now
        );

        $this->events->publish((int) $room['id'], EventLog::ROUND_FINISHED, [
            'round_number' => (int) $round['round_number'],
            'reason' => 'solved',
            'word' => (string) $round['word'],
            'winner' => [
                'guest_id' => (int) $guest['id'],
                'display_name' => (string) $guest['display_name'],
                'base_points' => $base,
                'streak_bonus' => $bonus,
                'points' => $base + $bonus,
            ],
        ], $now);
    }

    /** @return list<string> */
    private function wordPool(): array
    {
        $pool = $this->db->all('SELECT word FROM kalaqpar_words');

        if ($pool === []) {
            throw new KalaqparException(
                KalaqparException::CONFLICT,
                'بانک کلمات خالی است؛ `kalaqpar:seed` را اجرا کن.',
                503,
            );
        }

        return array_column($pool, 'word');
    }

    /** @return array<string, mixed> */
    private function requireOpenRoom(string $code): array
    {
        $room = $this->rooms->byCode($code);

        if ($room === null || $room['status'] === 'closed') {
            throw new KalaqparException(
                KalaqparException::NOT_FOUND,
                'این اتاق وجود ندارد یا بسته شده است.',
                404,
            );
        }

        return $room;
    }
}
