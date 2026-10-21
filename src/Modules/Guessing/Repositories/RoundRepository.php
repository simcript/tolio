<?php

namespace Src\Modules\Guessing\Repositories;

use Src\Modules\Guessing\Database\Connection;
use Src\Modules\Guessing\Exceptions\GuessingException;
use Src\Modules\Guessing\Services\GameRules;

/**
 * Round lifecycle persistence: rotated-letter reveals, guess submission and
 * snapshotting of round participants. Like RoomRepository, mutating methods
 * must run inside Connection::transaction().
 */
final class RoundRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function runningRound(int $roomId): ?array
    {
        return $this->db->one(
            'SELECT * FROM guessing_rounds WHERE room_id = ? AND status = "running" LIMIT 1',
            [$roomId]
        );
    }

    /** @return array<string, mixed>|null */
    public function latestRound(int $roomId): ?array
    {
        return $this->db->one(
            'SELECT * FROM guessing_rounds WHERE room_id = ? ORDER BY round_number DESC LIMIT 1',
            [$roomId]
        );
    }

    /**
     * Snapshots members into round participants; joining mid-game means the
     * new member plays from the *next* round, never retroactively.
     *
     * @param list<array<string, mixed>> $members
     */
    public function snapshotPlayers(int $roundId, array $members): void
    {
        foreach ($members as $member) {
            $this->db->run(
                'INSERT INTO guessing_round_players (round_id, guest_id) VALUES (?, ?)
                 ON CONFLICT(round_id, guest_id) DO NOTHING',
                [$roundId, (int) $member['guest_id']]
            );
        }
    }

    /**
     * Starts the next round inside the caller's transaction: picks a fresh
     * word, snapshot of participants and timing derived from GameRules.
     *
     * @param list<string> $wordPool
     * @param list<string> $exclude
     * @param list<array<string, mixed>> $members
     * @return array<string, mixed>
     */
    public function startRound(
        int $roomId,
        int $roundNumber,
        array $wordPool,
        array $exclude,
        array $members,
        int $startedAt,
        int $revealSeconds,
        int $graceSeconds,
        int $maxSeconds,
    ): array {
        $word = GameRules::pickWord($wordPool, $exclude);
        $length = GameRules::wordLength($word);
        $firstReveal = $startedAt + $revealSeconds;

        $this->db->run(
            'INSERT INTO guessing_rounds
                (room_id, round_number, word, revealed_count, status, started_at, next_reveal_at, ends_at)
             VALUES (?, ?, ?, 0, "running", ?, ?, ?)',
            [$roomId, $roundNumber, $word, $startedAt, $firstReveal, GameRules::roundEndsAt(
                $startedAt, $length, $revealSeconds, $graceSeconds, $maxSeconds
            )]
        );

        $roundId = (int) $this->db->pdo()->lastInsertId();
        $this->snapshotPlayers($roundId, $members);

        return $this->db->one('SELECT * FROM guessing_rounds WHERE id = ?', [$roundId]) ?? [];
    }

    /**
     * Reveals the next letter of $round when its reveal time has passed
     * (due-ness is re-checked inside the caller's transaction, which is
     * what makes concurrent workers safe).
     *
     * @return array{revealed: list<string>, changed: bool}
     */
    public function maybeReveal(array $round, int $now, int $revealSeconds): array
    {
        if ($round['status'] !== 'running' || $now < (int) $round['next_reveal_at']) {
            return ['revealed' => [], 'changed' => false];
        }

        $letters = mb_str_split(str_replace("\u{200C}", '', (string) $round['word']), 1, 'UTF-8');

        if (count($letters) <= (int) $round['revealed_count']) {
            return ['revealed' => [], 'changed' => false];
        }

        $this->db->run(
            'UPDATE guessing_rounds SET revealed_count = revealed_count + 1, next_reveal_at = ? WHERE id = ?',
            [$now + $revealSeconds, (int) $round['id']]
        );

        return [
            'revealed' => array_slice($letters, 0, (int) $round['revealed_count'] + 1),
            'changed' => true,
        ];
    }

    /**
     * Marks a timed-out round finished; scoring stays untouched because no
     * winner exists.
     */
    public function finishTimeout(array $round, int $now): void
    {
        $this->db->run(
            'UPDATE guessing_rounds
             SET status = "finished", finished_at = ?, finish_reason = "timeout"
             WHERE id = ? AND status = "running"',
            [$now, (int) $round['id']]
        );
    }

    /** @return array<string, mixed>|null */
    public function round(int $roundId): ?array
    {
        return $this->db->one('SELECT * FROM guessing_rounds WHERE id = ?', [$roundId]);
    }

    /**
     * Records an incorrect guess and enforces the round cooldown +
     * wrong-guess budget inside the caller's transaction.
     */
    public function recordWrongGuess(array $round, int $guestId, string $normalized, int $now, int $cooldown, int $wrongLimit): void
    {
        $participant = $this->db->one(
            'SELECT * FROM guessing_round_players WHERE round_id = ? AND guest_id = ?',
            [(int) $round['id'], $guestId]
        );

        if ($participant === null) {
            throw new GuessingException(
                GuessingException::NOT_MEMBER,
                'برای حدس زدن باید از ابتدای دور حاضر باشی.',
                403,
            );
        }

        if ($now - (int) $participant['last_guess_at'] < $cooldown) {
            $retry = $cooldown - ($now - (int) $participant['last_guess_at']);

            throw new GuessingException(
                GuessingException::RATE_LIMITED,
                'آرام‌تر! بین هر دو حدس چند ثانیه فاصله بگذار.',
                429,
                ['retry_after' => $retry],
            );
        }

        $wrong = (int) $participant['wrong_guesses'];
        if ($wrong >= $wrongLimit) {
            throw new GuessingException(
                GuessingException::RATE_LIMITED,
                'بودجه حدس‌های اشتباه این دور برای تو تمام شد.',
                429,
            );
        }

        $this->db->run(
            'INSERT INTO guessing_guesses (round_id, guest_id, guess, is_correct, created_at)
             VALUES (?, ?, ?, 0, ?)',
            [(int) $round['id'], $guestId, $normalized, $now]
        );

        $this->db->run(
            'UPDATE guessing_round_players
             SET wrong_guesses = wrong_guesses + 1, last_guess_at = ?
             WHERE round_id = ? AND guest_id = ?',
            [$now, (int) $round['id'], $guestId]
        );
    }

    /**
     * Consecutive winning rounds of $guest in $room immediately before
     * round_number $before, excluding the round being scored. A win streak
     * of 0 means the previous round was lost or skipped.
     */
    public function winStreakBefore(int $roomId, int $guestId, int $before): int
    {
        $winners = $this->db->all(
            'SELECT round_number, winner_guest_id FROM guessing_rounds
             WHERE room_id = ? AND round_number < ? AND status = "finished"
             ORDER BY round_number DESC, id DESC',
            [$roomId, $before]
        );

        $streak = 0;

        foreach ($winners as $winner) {
            if ((int) $winner['winner_guest_id'] !== $guestId) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    /** Score ledger insert (insert-only: rows are never updated). */
    public function recordScore(
        int $roomId,
        int $roundId,
        int $guestId,
        int $basePoints,
        int $streakBonus,
        int $now,
    ): void {
        $this->db->run(
            'INSERT INTO guessing_scores
                (room_id, round_id, guest_id, base_points, streak_bonus, points, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$roomId, $roundId, $guestId, $basePoints, $streakBonus, $basePoints + $streakBonus, $now]
        );
    }
}
