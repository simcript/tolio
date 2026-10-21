<?php

namespace Src\Modules\Guessing\Database;

use RuntimeException;
use Src\Modules\Guessing\Exceptions\GuessingException;

/**
 * Idempotent schema migrations for the Guessing SQLite database. Repeating a
 * migration run never rewrites data, so hosts upgrade with one command.
 */
final class Migrator
{
    /** @return string[] Names of applied migrations */
    public function migrate(Connection $connection): array
    {
        return $connection->transaction(function () use ($connection): array {
            $connection->run('CREATE TABLE IF NOT EXISTS guessing_migrations (
                name TEXT PRIMARY KEY,
                applied_at INTEGER NOT NULL
            )');

            $applied = array_column($connection->all('SELECT name FROM guessing_migrations'), 'name');
            $fresh = [];

            foreach (self::migrations() as $name => $statements) {
                if (in_array($name, $applied, true)) {
                    continue;
                }

                foreach (explode("\n\n", $statements) as $statement) {
                    $trimmed = trim($statement);
                    if ($trimmed !== '') {
                        $connection->run($trimmed);
                    }
                }

                $connection->run(
                    'INSERT INTO guessing_migrations (name, applied_at) VALUES (?, ?)',
                    [$name, time()]
                );
                $fresh[] = $name;
            }

            return $fresh;
        });
    }

    /** @return array<string, string> */
    private static function migrations(): array
    {
        return [
            '01_core_tables' => <<<'SQL'
CREATE TABLE IF NOT EXISTS guessing_guests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash TEXT NOT NULL UNIQUE,
    csrf_token TEXT NOT NULL,
    display_name TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    last_seen_at INTEGER NOT NULL
)


CREATE TABLE IF NOT EXISTS guessing_rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    host_guest_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'lobby',
    rounds_total INTEGER NOT NULL,
    current_round_number INTEGER NOT NULL DEFAULT 0,
    state_version INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL,
    active_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    closed_at INTEGER,
    close_reason TEXT
)


CREATE TABLE IF NOT EXISTS guessing_words (
    word TEXT PRIMARY KEY
)


CREATE TABLE IF NOT EXISTS guessing_rate_limits (
    guest_id INTEGER NOT NULL,
    scope TEXT NOT NULL,
    window_started_at INTEGER NOT NULL,
    hits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (guest_id, scope)
)
SQL,
            '02_game_tables' => <<<'SQL'
CREATE TABLE IF NOT EXISTS guessing_room_players (
    room_id INTEGER NOT NULL,
    guest_id INTEGER NOT NULL,
    joined_at INTEGER NOT NULL,
    active_at INTEGER NOT NULL,
    PRIMARY KEY (room_id, guest_id)
)


CREATE TABLE IF NOT EXISTS guessing_rounds (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL,
    round_number INTEGER NOT NULL,
    word TEXT NOT NULL,
    revealed_count INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'running',
    started_at INTEGER NOT NULL,
    next_reveal_at INTEGER NOT NULL,
    ends_at INTEGER NOT NULL,
    finished_at INTEGER,
    finish_reason TEXT,
    winner_guest_id INTEGER,
    UNIQUE (room_id, round_number)
)


CREATE TABLE IF NOT EXISTS guessing_round_players (
    round_id INTEGER NOT NULL,
    guest_id INTEGER NOT NULL,
    wrong_guesses INTEGER NOT NULL DEFAULT 0,
    last_guess_at INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (round_id, guest_id)
)


CREATE TABLE IF NOT EXISTS guessing_guesses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    round_id INTEGER NOT NULL,
    guest_id INTEGER NOT NULL,
    guess TEXT NOT NULL,
    is_correct INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL
)


CREATE TABLE IF NOT EXISTS guessing_scores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL,
    round_id INTEGER NOT NULL,
    guest_id INTEGER NOT NULL,
    base_points INTEGER NOT NULL DEFAULT 0,
    streak_bonus INTEGER NOT NULL DEFAULT 0,
    points INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    UNIQUE (round_id, guest_id)
)


CREATE TABLE IF NOT EXISTS guessing_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL,
    version INTEGER NOT NULL,
    type TEXT NOT NULL,
    payload TEXT NOT NULL DEFAULT '{}',
    created_at INTEGER NOT NULL,
    UNIQUE (room_id, version)
)
SQL,
            '03_indexes' => <<<'SQL'
CREATE INDEX IF NOT EXISTS guessing_rounds_due
    ON guessing_rounds (status, next_reveal_at, ends_at)

CREATE INDEX IF NOT EXISTS guessing_events_room
    ON guessing_events (room_id, id)

CREATE INDEX IF NOT EXISTS guessing_scores_leaderboard
    ON guessing_scores (guest_id, points)
SQL,
        ];
    }
}
