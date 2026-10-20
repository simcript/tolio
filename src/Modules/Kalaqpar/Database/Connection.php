<?php

namespace Src\Modules\Kalaqpar\Database;

use PDO;
use Src\Modules\Kalaqpar\Exceptions\KalaqparException;
use Throwable;

/**
 * Module-scoped SQLite connection used exclusively by Kalaqpar.
 *
 * The host application has no database layer, so this class owns the whole
 * lifecycle of the module database: path resolution, pragmas, and immediate
 * write transactions that keep every mutation (guests, guesses, rounds,
 * ledger entries) serialized behind SQLite's single-writer lock.
 */
final class Connection
{
    private const PRAGMAS = [
        'journal_mode = WAL',
        'busy_timeout = 5000',
        'foreign_keys = ON',
        'synchronous = NORMAL',
    ];

    private readonly PDO $pdo;

    public function __construct(string $path)
    {
        if (!is_file($path)) {
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException("Unable to create the Kalaqpar database directory ($directory).");
            }
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $this->pdo = new PDO('sqlite:' . $path, null, null, $options);

        foreach (self::PRAGMAS as $pragma) {
            $this->pdo->exec("PRAGMA $pragma");
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Runs $work inside a BEGIN IMMEDIATE transaction so long writes never
     * fail with "database is upgraded"... (WAL + IMMEDIATE serializes the
     * writers while readers stay free). When the caller already opened a
     * transaction, $work runs in it, allowing repositories to compose their
     * queries without re-entrant BEGINs.
     *
     * @template T
     * @param callable():T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        $own = !$this->pdo->inTransaction();

        if ($own) {
            $this->pdo->exec('BEGIN IMMEDIATE');
        }

        try {
            $result = $work();
        } catch (KalaqparException $domainError) {
            if ($own) {
                $this->pdo->rollBack();
            }
            throw $domainError;
        } catch (Throwable $error) {
            if ($own && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }

        if ($own) {
            $this->pdo->commit();
        }

        return $result;
    }

    /** @param array<int, mixed> $params */
    public function run(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /** @return array<string, mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }
}
