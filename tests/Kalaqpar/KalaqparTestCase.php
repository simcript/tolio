<?php

namespace Tests\Guessing;

use PHPUnit\Framework\TestCase;
use Src\Modules\Guessing\Database\Connection;
use Src\Modules\Guessing\Database\Migrator;
use Src\Modules\Guessing\Repositories\GuestRepository;
use Src\Modules\Guessing\Repositories\RoundRepository;
use Src\Modules\Guessing\Repositories\RoomRepository;
use Src\Modules\Guessing\Services\EventLog;
use Src\Modules\Guessing\Services\GameEngine;
use Src\Modules\Guessing\Services\GameOptions;
use Src\Modules\Guessing\Services\GameRules;
use Src\Modules\Guessing\Services\GameState;
use Src\Modules\Guessing\Services\GuestService;
use Src\Modules\Guessing\Services\RateLimiter;

/**
 * Shared plumbing for Guessing tests: every test runs against its own
 * temporary SQLite file (config override — the same pattern the smoke
 * script uses for Musician) and its own word list, so application data is
 * never touched.
 */
abstract class GuessingTestCase extends TestCase
{
    /** Game clock for the tests; advanceTime() moves it. */
    protected int $now = 1_700_000_000;

    private string $testDataDirectory = '';

    protected Connection $connection;

    protected RoomRepository $rooms;

    protected RoundRepository $rounds;

    protected GuestRepository $guests;

    /** @var list<array{0: string, 1: int}> captured (token, expires) pairs */
    protected array $issuedCookies = [];

    protected GuestService $guestService;

    protected GameEngine $engine;

    protected GameState $state;

    protected RateLimiter $limiter;

    protected GameOptions $options;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDataDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'kalaqpar-tests-'
            . bin2hex(random_bytes(6));
        if (!mkdir($this->testDataDirectory, 0775, true) && !is_dir($this->testDataDirectory)) {
            self::fail('Unable to create the isolated test directory.');
        }

        $this->bootFrameworkOnce();

        // config('kalaqpar.database') override (pattern of apps-smoke.php),
        // applied AFTER boot so the loader does not overwrite it.
        $_ENV['#_configs_#'] = $_ENV['#_configs_#'] ?? [];
        $_ENV['#_configs_#']['kalaqpar'] = [
            'database' => $this->databasePath(),
        ];

        $this->connection = new Connection($this->databasePath());

        $applied = (new Migrator())->migrate($this->connection);
        self::assertCount(3, $applied, 'All Guessing migrations must apply on a fresh database.');

        $this->rooms = new RoomRepository($this->connection);
        $this->rounds = new RoundRepository($this->connection);
        $this->guests = new GuestRepository($this->connection);

        $this->guestService = new GuestService(
            $this->connection,
            $this->guests,
            365,
            function (string $token, int $expires): void {
                $this->issuedCookies[] = [$token, $expires];
            }
        );

        $this->options = new GameOptions(
            defaultRounds: 3,
            maxRounds: 5,
            revealSeconds: 10,
            roundMaxSeconds: 120,
            finishGraceSeconds: 5,
            lobbyTimeout: 900,
            guessCooldown: 2,
            wrongGuessLimit: 3,
        );

        $this->limiter = new RateLimiter($this->connection);

        $this->engine = new GameEngine(
            $this->connection,
            $this->rooms,
            $this->rounds,
            new EventLog($this->connection),
            $this->guests,
            $this->options
        );

        $this->state = new GameState(
            $this->connection,
            $this->rooms,
            $this->rounds,
            $this->options
        );

        // Small, deterministic word pack per test.
        $this->seedWords(['کتاب', 'مدرسه', 'پرواز', 'دوچرخه', 'آسانسور']);
    }

    /** Boot constants (BASE_PATH, FOUNDATION…) once per process, like the smoke script does. */
    private static bool $booted = false;

    private static function bootFrameworkOnce(): void
    {
        if (self::$booted) {
            return;
        }

        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        new \Pano\Foundation\Boot(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR,
            new \Src\Foundation\DefaultFoundation()
        );
        self::$booted = true;
    }

    protected function tearDown(): void
    {
        // No test artifacts left behind; no application data touched.
        $files = glob($this->testDataDirectory . '/*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        if (is_dir($this->testDataDirectory)) {
            @rmdir($this->testDataDirectory);
        }
        $_COOKIE = [];
        parent::tearDown();
    }

    protected function databasePath(): string
    {
        return $this->testDataDirectory . DIRECTORY_SEPARATOR . 'kalaqpar.sqlite';
    }

    protected function seedWords(array $words): void
    {
        foreach ($words as $word) {
            $this->connection->run(
                'INSERT OR IGNORE INTO kalaqpar_words (word) VALUES (?)',
                [GameRules::normalize(trim($word))]
            );
        }
    }

    /**
     * Creates a persistent guest row directly (identity is cookie-driven in
     * HTTP) and returns the guest row. The raw token is a valid 64-hex
     * cookie value derived from $key so tests can replay it.
     *
     * @return array<string, mixed>
     */
    protected function guest(string $key, string $name = ''): array
    {
        $rawToken = self::cookieTokenFor($key);

        $existing = $this->guests->byTokenHash(hash('sha256', $rawToken));

        if ($existing !== null) {
            return $existing;
        }

        $created = $this->guests->create(hash('sha256', $rawToken));

        if ($name !== '') {
            $this->connection->run(
                'UPDATE kalaqpar_guests SET display_name = ? WHERE id = ?',
                [$name, $created['id']]
            );
        }

        $row = $this->guests->byId($created['id']);
        self::assertNotNull($row, 'Guest row must exist after creation.');

        return $row;
    }

    /** The raw cookie token a client with identity $key would present. */
    protected static function cookieTokenFor(string $key): string
    {
        return hash('sha256', 'kalaqpar-test-cookie-' . $key);
    }

    /**
     * Room wired for a game: host member plus the given guest ids as extra
     * members, game started through the engine.
     *
     * @param list<int> $extraGuestIds
     * @return array{room: array<string, mixed>, host: array<string, mixed>}
     */
    protected function readyRoom(string $hostKey, string $hostName, array $extraGuestIds): array
    {
        $host = $this->guest($hostKey, $hostName);
        $created = $this->engine->createRoom($host, ['name' => 'اتاق تست', 'rounds' => 3], $this->now);

        $room = $this->rooms->byCode($created['code']);
        self::assertNotNull($room, 'Room row must exist after creation.');

        foreach ($extraGuestIds as $guestId) {
            $this->rooms->join((int) $room['id'], $guestId, $this->now);
        }

        $this->engine->startGame($host, (string) $room['code'], $this->now);
        $room = $this->rooms->byCode((string) $room['code']);
        self::assertNotNull($room);
        self::assertSame('playing', $room['status']);
        self::assertSame(1, (int) $room['current_round_number']);

        return ['room' => $room, 'host' => $host];
    }

    /** The room's most recent round row. */
    protected function currentRound(int $roomId): array
    {
        $round = $this->rounds->latestRound($roomId);
        self::assertNotNull($round, 'Round row must exist.');

        return $round;
    }

    /** @return list<array<string, mixed>> */
    protected function events(int $roomId): array
    {
        return (new EventLog($this->connection))->since($roomId, 0, 1000);
    }

    protected function advanceTime(int $seconds): void
    {
        $this->now += $seconds;
    }
}
