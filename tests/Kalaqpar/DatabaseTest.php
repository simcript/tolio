<?php

namespace Tests\Guessing;

use Pano\Foundation\Request;
use Src\Modules\Guessing\Database\WordSeeder;
use Src\Modules\Guessing\Exceptions\GuessingException;

/**
 * Database layer guarantees: schema idempotency, seed idempotency and
 * rate-limit windows — all on the isolated per-test database.
 */
final class DatabaseTest extends GuessingTestCase
{
    public function test_migrations_are_idempotent(): void
    {
        // setUp() already applied them once; a re-run changes nothing.
        $applied = (new \Src\Modules\Guessing\Database\Migrator())->migrate($this->connection);
        self::assertSame([], $applied, 'Second migrate run must be a no-op.');

        $priorGuests = $this->connection->run('SELECT COUNT(*) FROM kalaqpar_guests')->fetchColumn();
        $this->guest('guest-a');
        (new \Src\Modules\Guessing\Database\Migrator())->migrate($this->connection);
        $afterGuests = $this->connection->run('SELECT COUNT(*) FROM kalaqpar_guests')->fetchColumn();
        self::assertSame((int) $priorGuests + 1, (int) $afterGuests, 'Schema reruns never touch data.');
    }

    public function test_word_seeder_is_idempotent_and_scoped(): void
    {
        $pack = __DIR__ . '/../../src/Modules/Guessing/Files/words.json';
        $seeder = new WordSeeder($pack);

        $first = $seeder->seed($this->connection);
        self::assertGreaterThan(0, $first['inserted'], 'The module word pack seeds the database.');

        $second = $seeder->seed($this->connection);
        self::assertSame(0, $second['inserted'], 'Re-running the seed inserts nothing new.');
        self::assertSame($first['total'], $second['total'], 'The pack size is stable.');
    }

    public function test_rate_limit_windows_are_per_guest_and_per_scope(): void
    {
        $guest = $this->guest('guest-a');
        $other = $this->guest('guest-b');

        for ($i = 0; $i < 4; $i++) {
            $this->limiter->assertLimit((int) $guest['id'], 'room.create', 4, 60, $this->now + $i);
        }

        try {
            $this->limiter->assertLimit((int) $guest['id'], 'room.create', 4, 60, $this->now + 4);
            self::fail('Fifth hit inside the window must be limited.');
        } catch (GuessingException $error) {
            self::assertSame(GuessingException::RATE_LIMITED, $error->error);
            self::assertSame('room.create', $error->body()['scope']);
        }

        // Another guest is unaffected, and a new window resets the count.
        $this->limiter->assertLimit((int) $other['id'], 'room.create', 4, 60, $this->now + 4);
        $this->limiter->assertLimit((int) $guest['id'], 'room.join', 4, 60, $this->now + 4);
        $this->limiter->assertLimit((int) $guest['id'], 'room.create', 4, 60, $this->now + 121);
    }

    public function test_database_sits_outside_the_public_directory(): void
    {
        // CE integration guard: the module database must never land in public/.
        $this->assertStringNotContainsString(
            DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR,
            $this->databasePath()
        );
    }
}
