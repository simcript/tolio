<?php
namespace Src\Modules\Kalaqpar\Database;

use PDO;
use PDOException;
use Throwable;

final class Connection
{
    public readonly PDO $pdo;
    private bool $writing = false;

    public function __construct(public readonly string $path)
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create private game storage.');
        }
        $realDirectory = realpath($directory);
        $public = realpath(dirname(__DIR__, 4) . '/public');
        if ($public && $realDirectory && ($realDirectory === $public || str_starts_with($realDirectory, $public . DIRECTORY_SEPARATOR))) {
            throw new \RuntimeException('Game storage must be outside public.');
        }
        $this->pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->pdo->exec('PRAGMA busy_timeout=1000; PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;');
        @chmod($path, 0600);
    }

    public function run(string $sql, array $params = []): \PDOStatement
    {
        $s = $this->pdo->prepare($sql);
        $s->execute($params);
        return $s;
    }

    public function one(string $sql, array $params = []): ?array { return $this->run($sql, $params)->fetch() ?: null; }
    public function all(string $sql, array $params = []): array { return $this->run($sql, $params)->fetchAll(); }
    public function id(): int { return (int) $this->pdo->lastInsertId(); }

    // PDO inTransaction() is not used to track manually issued SQLite BEGIN.
    public function atomic(callable $work): mixed
    {
        if ($this->writing) { return $work(); }
        for ($attempt = 0; ; $attempt++) {
            try { $this->pdo->exec('BEGIN IMMEDIATE'); break; }
            catch (PDOException $e) {
                if (!in_array((int) ($e->errorInfo[1] ?? 0), [5, 6], true) || $attempt >= 2) { throw $e; }
                usleep(random_int(10000, 40000));
            }
        }
        $this->writing = true;
        try {
            $value = $work();
            $this->pdo->exec('COMMIT');
            return $value;
        } catch (Throwable $e) {
            $this->pdo->exec('ROLLBACK');
            throw $e;
        } finally { $this->writing = false; }
    }

    public function snapshot(callable $work): mixed
    {
        $this->pdo->exec('BEGIN');
        try { $value = $work(); $this->pdo->exec('COMMIT'); return $value; }
        catch (Throwable $e) { $this->pdo->exec('ROLLBACK'); throw $e; }
    }
}
