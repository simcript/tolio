<?php

namespace Tests\Guessing;

use Src\Modules\Guessing\Exceptions\GuessingException;
use Src\Modules\Guessing\Services\EventLog;
use Src\Modules\Guessing\Services\GameRules;

/**
 * The full round lifecycle of the engine, driven by the injected clock so
 * every timing scenario (reveals, timeouts, round chaining, idle rooms,
 * streaks, concurrency) is deterministic.
 */
final class GameEngineTest extends GuessingTestCase
{
    public function test_game_starts_in_a_wired_lobby_and_first_round_launches(): void
    {
        $wired = $this->readyRoom('guest-A', 'میزبان', [$this->guest('guest-B', 'بازیکن دو')['id']]);
        $room = $wired['room'];

        self::assertSame(1, (int) $room['current_round_number']);

        $round = $this->currentRound((int) $room['id']);
        self::assertSame('running', $round['status']);
        self::assertSame(0, (int) $round['revealed_count']);
        self::assertSame($this->now, (int) $round['started_at']);

        // Word length determines the natural end; per config: len*10 + 5.
        self::assertSame(
            $this->now + GameRules::wordLength((string) $round['word']) * 10 + 5,
            (int) $round['ends_at']
        );
    }

    public function test_reveals_advance_one_letter_per_interval(): void
    {
        $wired = $this->readyRoom('guest-A', 'میزبان', [$this->guest('guest-B', 'بازیکن دو')['id']]);
        $room = $wired['room'];
        $round = $this->currentRound((int) $room['id']);
        $length = GameRules::wordLength((string) $round['word']);

        $this->engine->tick($this->now + 1);
        self::assertSame(0, (int) $this->currentRound((int) $room['id'])['revealed_count']);

        $this->engine->tick($this->now + 10);
        self::assertSame(1, (int) $this->currentRound((int) $room['id'])['revealed_count']);

        $this->engine->tick($this->now + 19);
        self::assertSame(1, (int) $this->currentRound((int) $room['id'])['revealed_count'],
            'One tick, one reveal: no double jumps with coarser pulses.');

        $this->engine->tick($this->now + 20);
        self::assertSame(2, (int) $this->currentRound((int) $room['id'])['revealed_count']);

        // Revealing past the word is capped by the engine. The pulses above
        // long outlive the first round (rounds chain while the room is
        // live), so the cap is asserted on every round's own row.
        for ($i = 0; $i < $length + 5; $i++) {
            $this->engine->tick($this->now + 30 + ($i * 10));
        }

        $played = $this->connection->all(
            'SELECT word, revealed_count FROM kalaqpar_rounds WHERE room_id = ?',
            [(int) $room['id']]
        );
        self::assertNotEmpty($played);

        foreach ($played as $row) {
            self::assertLessThanOrEqual(
                GameRules::wordLength((string) $row['word']),
                (int) $row['revealed_count'],
                'Revealing is capped at the word length.'
            );
        }

        $first = $this->connection->one(
            'SELECT revealed_count FROM kalaqpar_rounds WHERE id = ?',
            [(int) $round['id']]
        );
        self::assertNotNull($first);
        self::assertSame($length, (int) $first['revealed_count'],
            'The first round followed the full reveal cadence to the last letter.');
    }

    public function test_correct_guess_finishes_the_round_scores_the_ledger_and_reveals_the_word(): void
    {
        $host = $this->guest('guest-A', 'میزبان');
        $player = $this->guest('guest-B', 'بازیکن دو');
        $wired = $this->readyRoom('guest-A', 'میزبان', [$player['id']]);
        $room = $wired['room'];

        $round = $this->currentRound((int) $room['id']);
        $wordBefore = (string) $round['word'];

        $result = $this->engine->guess($host, (string) $room['code'], $wordBefore . ' ', $this->now + 3);

        self::assertTrue($result['correct']);
        self::assertTrue($result['finished']);

        $row = $this->connection->one(
            'SELECT * FROM kalaqpar_scores WHERE round_id = ?',
            [(int) $round['id']]
        );
        self::assertNotNull($row, 'Exactly one score-ledger row per winning round.');

        $base = GameRules::basePoints(0);
        $bonus = GameRules::streakBonus(1);
        self::assertSame($base, (int) $row['base_points']);
        self::assertSame($bonus, (int) $row['streak_bonus']);
        self::assertSame($base + $bonus, (int) $row['points']);
    }

    public function test_two_consecutive_wins_pay_a_streak_bonus(): void
    {
        $host = $this->guest('guest-A', 'میزبان');
        $this->guest('guest-B', 'بازیکن دو');
        $wired = $this->readyRoom('guest-A', 'میزبان', [$this->guest('guest-B', 'بازیکن دو')['id']]);
        $code = (string) $wired['room']['code'];
        $roomId = (int) $wired['room']['id'];

        // Round 1: solve now.
        $firstWord = (string) $this->currentRound($roomId)['word'];
        $this->engine->guess($host, $code, $firstWord, $this->now + 3);

        // Let round 2 start.
        $this->engine->tick($this->now + 200);
        $second = $this->currentRound($roomId);
        self::assertSame(2, (int) $second['round_number']);

        $result = $this->engine->guess($host, $code, (string) $second['word'], $this->now + 203);
        self::assertTrue($result['correct']);

        $row = $this->connection->one(
            'SELECT s.streak_bonus FROM kalaqpar_scores s
             JOIN kalaqpar_rounds r ON r.id = s.round_id
             WHERE r.round_number = 2 AND s.guest_id = ?',
            [(int) $host['id']]
        );
        self::assertNotNull($row);
        self::assertSame(GameRules::streakBonus(2), (int) $row['streak_bonus']);
    }

    public function test_timeout_round_scores_nobody_and_launches_next_round(): void
    {
        $host = $this->guest('guest-A', 'میزبان');
        $player = $this->guest('guest-B', 'بازیکن دو');
        $wired = $this->readyRoom('guest-A', 'میزبان', [$player['id']]);
        $room = $wired['room'];
        $round = $this->currentRound((int) $room['id']);

        // Jump past ends_at and let one worker pulse finish the round.
        $this->engine->tick((int) $round['ends_at'] + 1);

        $finished = $this->connection->one(
            'SELECT * FROM kalaqpar_rounds WHERE id = ?',
            [(int) $round['id']]
        );
        self::assertSame('finished', $finished['status']);
        self::assertSame('timeout', $finished['finish_reason']);
        self::assertNull($finished['winner_guest_id']);

        self::assertCount(
            0,
            $this->connection->all('SELECT * FROM kalaqpar_scores WHERE round_id = ?',
                [(int) $round['id']]),
            'Timed-out rounds never touch the ledger.'
        );

        // Next pulse immediately starts round 2.
        $this->engine->tick((int) $round['ends_at'] + 2);
        $roomRow = $this->rooms->byId((int) $room['id']);
        self::assertSame(2, (int) $roomRow['current_round_number']);
    }

    public function test_room_closes_when_the_last_round_finishes(): void
    {
        $wired = $this->readyRoom('guest-A', 'میزبان', [$this->guest('guest-B', 'بازیکن دو')['id']]);
        $room = $wired['room'];

        // Run the whole game to completion: whatever happens inside, worker pulses close the room.
        for ($i = 0; $i < 60; $i++) {
            $this->engine->tick($this->now);
            $this->advanceTime(10);
        }

        $closed = $this->rooms->byId((int) $room['id']);
        self::assertSame('closed', $closed['status']);
        self::assertSame('completed', $closed['close_reason']);
    }

    public function test_completion_room_fires_room_closed_event_with_reason(): void
    {
        $wired = $this->readyRoom('guest-A', 'میزبان', [$this->guest('guest-B', 'بازیکن دو')['id']]);
        $roomId = (int) $wired['room']['id'];

        for ($i = 0; $i < 60; $i++) {
            $this->engine->tick($this->now);
            $this->advanceTime(10);
        }

        $types = array_column($this->events($roomId), 'type');
        self::assertContains(EventLog::ROOM_CLOSED, $types);
        self::assertContains(EventLog::ROUND_FINISHED, $types);
        self::assertContains(EventLog::ROUND_STARTED, $types);
    }

    public function test_wrong_guess_counts_and_cooldown_blocks_rapid_spam(): void
    {
        $player = $this->guest('guest-B', 'بازیکن دو');
        $wired = $this->readyRoom('guest-A', 'میزبان', [$player['id']]);
        $room = $wired['room'];

        $goodWord = (string) $this->currentRound((int) $room['id'])['word'];
        $badGuess = str_starts_with('ک', $goodWord[0] ?? '') ? 'زورق' : 'زورق';

        $this->engine->guess($player, (string) $room['code'], $badGuess, $this->now);

        $row = $this->connection->one(
            'SELECT * FROM kalaqpar_round_players WHERE round_id = ? AND guest_id = ?',
            [(int) $this->currentRound((int) $room['id'])['id'], (int) $player['id']]
        );
        self::assertNotNull($row);
        self::assertSame(1, (int) $row['wrong_guesses']);

        try {
            $this->engine->guess($player, (string) $room['code'], 'زوگ', $this->now + 1);
            self::fail('Cooldown must block a second guess within two seconds.');
        } catch (GuessingException $error) {
            self::assertSame(GuessingException::RATE_LIMITED, $error->error);
        }

        $this->engine->guess($player, (string) $room['code'], 'زوگ', $this->now + 2);
        $row = $this->connection->one(
            'SELECT * FROM kalaqpar_round_players WHERE round_id = ? AND guest_id = ?',
            [(int) $this->currentRound((int) $room['id'])['id'], (int) $player['id']]
        );
        self::assertSame(2, (int) $row['wrong_guesses']);
    }

    public function test_wrong_guess_budget_is_enforced_per_round(): void
    {
        $player = $this->guest('guest-B', 'بازیکن دو');
        $wired = $this->readyRoom('guest-A', 'میزبان', [$player['id']]);
        $room = $wired['room'];

        // Budget = 3 wrong guesses; every one outside the 2s cooldown.
        $this->engine->guess($player, (string) $room['code'], 'زوگ', $this->now);
        $this->engine->guess($player, (string) $room['code'], 'زوگ', $this->now + 2);
        $this->engine->guess($player, (string) $room['code'], 'زوگ', $this->now + 4);

        try {
            $this->engine->guess($player, (string) $room['code'], 'زوگ', $this->now + 6);
            self::fail('Wrong-guess budget must stop the fourth wrong guess.');
        } catch (GuessingException $error) {
            self::assertSame(GuessingException::RATE_LIMITED, $error->error);
        }
    }

    public function test_guess_after_the_deadline_finishes_the_round_as_a_timeout(): void
    {
        $player = $this->guest('guest-B', 'بازیکن دو');
        $wired = $this->readyRoom('guest-A', 'میزبان', [$player['id']]);
        $room = $wired['room'];
        $round = $this->currentRound((int) $room['id']);

        try {
            $this->engine->guess($player, (string) $room['code'], (string) $round['word'], (int) $round['ends_at'] + 3);
            self::fail('Guesses must not score after ends_at.');
        } catch (GuessingException $error) {
            self::assertSame(GuessingException::TOO_LATE, $error->error);
        }

        $finished = $this->connection->one(
            'SELECT status, finish_reason FROM kalaqpar_rounds WHERE id = ?',
            [(int) $round['id']]
        );
        self::assertSame('finished', $finished['status']);
        self::assertSame('timeout', $finished['finish_reason']);
    }

    public function test_joining_mid_game_does_not_retroactively_participate(): void
    {
        $late = $this->guest('guest-C', 'دیررس');
        $wired = $this->readyRoom('guest-A', 'میزبان', [$this->guest('guest-B', 'بازیکن دو')['id']]);
        $room = $wired['room'];

        $this->rooms->join((int) $room['id'], (int) $late['id'], $this->now + 1);

        $round = $this->currentRound((int) $room['id']);
        $participants = $this->connection->all(
            'SELECT guest_id FROM kalaqpar_round_players WHERE round_id = ?',
            [(int) $round['id']]
        );
        self::assertCount(2, $participants, 'Round snapshot created before the late join.');
        self::assertNotContains((int) $late['id'], array_column($participants, 'guest_id'));

        try {
            $this->engine->guess($late, (string) $room['code'], 'زوگ', $this->now + 2);
            self::fail('Late joiners cannot guess until the next round.');
        } catch (GuessingException $error) {
            self::assertSame(GuessingException::NOT_MEMBER, $error->error);
        }

        // ...but the engine wraps them into the next round automatically.
        $this->engine->tick((int) $round['ends_at'] + 1);      // closes round 1
        $this->engine->tick((int) $round['ends_at'] + 2);      // starts round 2
        $next = $this->currentRound((int) $room['id']);
        self::assertSame(2, (int) $next['round_number']);

        $participants = $this->connection->all(
            'SELECT guest_id FROM kalaqpar_round_players WHERE round_id = ?',
            [(int) $next['id']]
        );
        self::assertContains((int) $late['id'], array_column($participants, 'guest_id'));
    }

    public function test_two_worker_pulses_never_double_process_a_round(): void
    {
        $wired = $this->readyRoom('guest-A', 'میزبان', [$this->guest('guest-B', 'بازیکن دو')['id']]);
        $roomId = (int) $wired['room']['id'];

        $at = $this->now + 10;
        $this->engine->tick($at); // worker one reveal pulse

        $versionsFirst = (int) $this->rooms->byId($roomId)['state_version'];

        $this->engine->tick($at + 2); // worker two, later but still within interval

        $versionsSecond = (int) $this->rooms->byId($roomId)['state_version'];
        self::assertSame($versionsFirst, $versionsSecond,
            'A pulse that is not due must not rewrite the room version.');

        // At the exact boundary the count stays exactly 1 letter.
        $round = $this->currentRound($roomId);
        self::assertSame(1, (int) $round['revealed_count']);
    }

    public function test_version_is_monotonic_and_event_payloads_never_leak_the_word_early(): void
    {
        $host = $this->guest('guest-A', 'میزبان');
        $player = $this->guest('guest-B', 'بازیکن دو');
        $wired = $this->readyRoom('guest-A', 'میزبان', [$player['id']]);
        $room = $wired['room'];
        $roomId = (int) $room['id'];
        $secret = (string) $this->currentRound($roomId)['word'];

        // One reveal pulse so the feed contains letters.
        $this->engine->tick($this->now + 12);

        $lastVersion = 0;
        foreach ($this->events($roomId) as $event) {
            $version = (int) $event['version'];
            self::assertGreaterThan($lastVersion, $version, 'Versions must be strictly increasing.');
            $lastVersion = $version;

            $payload = json_decode((string) $event['payload'], true) ?? [];
            if (in_array($event['type'], [EventLog::ROUND_STARTED, EventLog::LETTER_REVEALED], true)) {
                self::assertArrayNotHasKey('word', $payload,
                    'The secret must only be published when the round ends.');
            }
        }

        $this->engine->guess($host, (string) $room['code'], $secret, $this->now + 13);

        $types = [];
        foreach ($this->events($roomId) as $event) {
            $types[] = $event['type'];

            if ($event['type'] === EventLog::ROUND_FINISHED) {
                self::assertSame($secret, json_decode((string) $event['payload'], true)['word']);
            }
        }

        self::assertContains(EventLog::ROUND_STARTED, $types);
        self::assertContains(EventLog::LETTER_REVEALED, $types);
        self::assertContains(EventLog::ROUND_FINISHED, $types);
    }

    public function test_idle_lobby_is_retired(): void
    {
        $host = $this->guest('guest-A', 'میزبان');
        $created = $this->engine->createRoom($host, ['name' => 'متروک'], $this->now);
        $room = $this->rooms->byCode($created['code']);

        // No activity: retreat past the 900s lobby timeout.
        $this->engine->tick($this->now + 901);

        $room = $this->rooms->byId((int) $room['id']);
        self::assertSame('closed', $room['status']);
        self::assertSame('idle', $room['close_reason']);
    }

    public function test_leave_room_empty_closes_it_and_host_changes_hand(): void
    {
        $host = $this->guest('guest-A', 'میزبان');
        $player = $this->guest('guest-B', 'بازیکن دو');
        $wired = $this->readyRoom('guest-A', 'میزبان', [$player['id']]);
        $room = $wired['room'];

        $this->engine->leaveRoom($host, (string) $room['code'], $this->now + 1);

        $row = $this->rooms->byId((int) $room['id']);
        self::assertSame((int) $player['id'], (int) $row['host_guest_id'],
            'The remaining senior member takes over as host.');

        $this->engine->leaveRoom($player, (string) $room['code'], $this->now + 2);
        $row = $this->rooms->byId((int) $room['id']);
        self::assertSame('closed', $row['status']);
        self::assertSame('empty', $row['close_reason']);
    }

    public function test_state_snapshot_hides_the_word_and_reports_progress(): void
    {
        $host = $this->guest('guest-A', 'میزبان');
        $player = $this->guest('guest-B', 'بازیکن دو');
        $wired = $this->readyRoom('guest-A', 'میزبان', [$player['id']]);
        $room = $wired['room'];

        $this->engine->tick($this->now + 10); // one letter revealed
        $secret = (string) $this->currentRound((int) $room['id'])['word'];

        $snapshot = $this->state->forRoom((string) $room['code'], $player, 0, $this->now + 10);

        self::assertTrue($snapshot['ok']);
        self::assertSame('playing', $snapshot['room']['status']);
        self::assertNotNull($snapshot['round']);

        // Only the revealed prefix — never the whole word.
        self::assertSame(
            [mb_substr($secret, 0, 1, 'UTF-8')],
            $snapshot['round']['revealed']
        );

        self::assertArrayNotHasKey('word', $snapshot['round']);
        self::assertSame(1, $snapshot['round']['revealed_count']);
        self::assertSame(3, $snapshot['round']['guesses_left'],
            'Budget matches the configured wrong-guess limit.');
    }
}
