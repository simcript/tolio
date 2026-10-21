<?php
namespace Src\Modules\Kalaqpar\Database;

// Follows the host Guessing module's versioned, transactional migration workflow.
final class Migrator
{
    public function migrate(Connection $db): void
    {
        $db->atomic(function () use ($db) {
            $db->run('CREATE TABLE IF NOT EXISTS kalaqpar_migrations (version INTEGER PRIMARY KEY, applied_at INTEGER NOT NULL)');
            if (!$db->one('SELECT version FROM kalaqpar_migrations WHERE version=1')) {
                $db->pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
                $db->run('INSERT INTO kalaqpar_migrations VALUES (1, ?)', [(int) floor(microtime(true) * 1000)]);
            }
            if (!$db->one('SELECT version FROM kalaqpar_migrations WHERE version=2')) {
                $db->run('ALTER TABLE kalaqpar_rounds ADD COLUMN category INTEGER CHECK(category IN (0,1))');
                $db->run('UPDATE kalaqpar_rounds SET category=(SELECT can_fly FROM kalaqpar_items i WHERE i.id=kalaqpar_rounds.item_id)');
                $db->run('INSERT INTO kalaqpar_migrations VALUES (2, ?)', [(int) floor(microtime(true) * 1000)]);
            }
        });
    }
}
