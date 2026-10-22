<?php
namespace Src\Modules\Kalaqpar\Database;

use Src\Modules\Kalaqpar\Services\Invitation;

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
            if (!$db->one('SELECT version FROM kalaqpar_migrations WHERE version=3')) {
                $db->run('ALTER TABLE kalaqpar_worker_status ADD COLUMN cleanup_at INTEGER NOT NULL DEFAULT 0');
                $db->run('INSERT INTO kalaqpar_migrations VALUES (3, ?)', [(int) floor(microtime(true) * 1000)]);
            }
            if (!$db->one('SELECT version FROM kalaqpar_migrations WHERE version=4')) {
                $db->run("ALTER TABLE kalaqpar_rooms ADD COLUMN legacy_invitation TEXT CHECK(legacy_invitation IS NULL OR (typeof(legacy_invitation)='text' AND length(legacy_invitation)=48 AND legacy_invitation NOT GLOB '*[^a-f0-9]*'))");
                $db->run("UPDATE kalaqpar_rooms SET legacy_invitation=invitation,invitation=NULL WHERE mode='private' AND length(invitation)=48");
                foreach ($db->all("SELECT id FROM kalaqpar_rooms WHERE mode='private' AND invitation IS NULL") as $room) {
                    Invitation::allocate($db, fn ($code) => $db->run('UPDATE kalaqpar_rooms SET invitation=? WHERE id=?', [$code, $room['id']]));
                }
                if ($db->one("SELECT 1 FROM kalaqpar_rooms WHERE mode='private' AND (typeof(invitation)<>'text' OR invitation NOT GLOB '[1-9][0-9][0-9][0-9][0-9][0-9]') LIMIT 1")) {
                    throw new \RuntimeException('Unexpected existing invitation format; migration rolled back.');
                }
                $db->pdo->exec(file_get_contents(__DIR__ . '/invitations.sql'));
                $db->run('INSERT INTO kalaqpar_migrations VALUES (4, ?)', [(int) floor(microtime(true) * 1000)]);
            }
        });
    }
}
