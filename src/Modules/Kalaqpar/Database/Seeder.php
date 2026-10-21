<?php
namespace Src\Modules\Kalaqpar\Database;

final class Seeder
{
    public function seed(Connection $db): void
    {
        $items = Content::catalogue();
        foreach ($items as $i) {
            if (!is_file(dirname(__DIR__) . '/Files/items/' . $i['asset'] . '.svg')) {
                throw new \RuntimeException('Missing bundled image: run the production artwork build described in OPERATIONS.md.');
            }
        }
        $db->atomic(function () use ($db, $items) {
            foreach ($items as $i) {
                $db->run('INSERT INTO kalaqpar_items(name,asset,can_fly) VALUES (?,?,?) ON CONFLICT(name) DO UPDATE SET asset=excluded.asset,can_fly=excluded.can_fly', [$i['name'], $i['asset'], $i['can_fly']]);
            }
        });
    }
}
