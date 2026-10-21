<?php

namespace Src\Modules\Guessing;

use Src\Modules\Guessing\Database\Connection;
use Src\Modules\Guessing\Database\Migrator;
use Src\Modules\Guessing\Database\WordSeeder;
use Src\Modules\Guessing\Repositories\GuestRepository;
use Src\Modules\Guessing\Repositories\RoundRepository;
use Src\Modules\Guessing\Repositories\RoomRepository;
use Src\Modules\Guessing\Services\EventLog;
use Src\Modules\Guessing\Services\GameEngine;
use Src\Modules\Guessing\Services\GameOptions;
use Src\Modules\Guessing\Services\GameState;
use Src\Modules\Guessing\Services\GuestService;
use Src\Modules\Guessing\Services\RateLimiter;

/**
 * Wiring for the Guessing module: one place that resolves the module SQLite
 * connection and composes repositories/services. Handlers and commands ask
 * this factory for ready-made collaborators, mirroring how Musician
 * resolves its services directly in its handlers.
 */
final class GuessingFactory
{
    private ?Connection $connection = null;

    /**
     * SQLite path: env override, else the module-private Files directory
     * (which sits outside public/ like Musician's data files).
     *
     * @return string database file path
     */
    public function databasePath(string $modulePath): string
    {
        $configured = trim((string) (config('guessing.database') ?? ''));

        if ($configured !== '' && self::isAbsolute($configured)) {
            return $configured;
        }

        return $modulePath . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'guessing.sqlite';
    }

    /** Absolute on Windows (C:\ or C:/) or POSIX (/…). */
    private static function isAbsolute(string $path): bool
    {
        $length = strlen($path);

        if ($length > 0 && ($path[0] === '/' || $path[0] === '\\')) {
            return true;
        }

        return $length > 2
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && ($path[2] === '/' || $path[2] === '\\');
    }

    public function connection(string $modulePath): Connection
    {
        if ($this->connection === null) {
            $this->connection = new Connection($this->databasePath($modulePath));
        }

        return $this->connection;
    }

    public function options(): GameOptions
    {
        return GameOptions::fromConfig();
    }

    public function migrator(): Migrator
    {
        return new Migrator();
    }

    public function seeder(): WordSeeder
    {
        $modulePath = __DIR__;

        return new WordSeeder($modulePath . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'words.json');
    }

    public function guestService(Connection $connection, GuestRepository $guests): GuestService
    {
        return new GuestService(
            $connection,
            $guests,
            (int) (config('guessing.guest_ttl_days') ?? 365)
        );
    }

    /** @return array{connection: Connection, guests: GuestRepository, rooms: RoomRepository,
    *               rounds: RoundRepository, events: EventLog, engine: GameEngine, state: GameState,
    *               limiter: RateLimiter, options: GameOptions} */
    public function services(string $modulePath): array
    {
        $connection = $this->connection($modulePath);
        $rooms = new RoomRepository($connection);
        $rounds = new RoundRepository($connection);
        $events = new EventLog($connection);
        $options = $this->options();

        return [
            'connection' => $connection,
            'guests' => new GuestRepository($connection),
            'rooms' => $rooms,
            'rounds' => $rounds,
            'events' => $events,
            'engine' => new GameEngine($connection, $rooms, $rounds, $events, new GuestRepository($connection), $options),
            'state' => new GameState($connection, $rooms, $rounds, $options),
            'limiter' => new RateLimiter($connection),
            'options' => $options,
        ];
    }
}
