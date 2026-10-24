<?php
namespace Src\Modules\Kalaqpar\Database;

final class Seeder
{
    public function seed(Connection $db): void
    {
        $items = Content::catalogue();
        $names = [];
        $assets = [];
        foreach ($items as $item) {
            $name = self::normalizedName($item['name']);
            if ($name === '' || isset($names[$name]) || isset($assets[$item['asset']])) {
                throw new \RuntimeException('Duplicate or empty bundled item name or image.');
            }
            if (!in_array($item['can_fly'], [0, 1], true) || !preg_match('/^[a-f0-9]{24}$/D', $item['asset'])) {
                throw new \RuntimeException('Invalid bundled item classification or image identifier.');
            }
            $names[$name] = $item['name'];
            $assets[$item['asset']] = true;
            if (!is_file(dirname(__DIR__) . '/Files/items/' . $item['asset'] . '.svg')) {
                throw new \RuntimeException('Missing bundled image: run the production artwork build described in OPERATIONS.md.');
            }
        }
        $db->atomic(function () use ($db, $items) {
            $existingNames = [];
            foreach ($db->all('SELECT name FROM kalaqpar_items') as $existing) {
                $name = self::normalizedName($existing['name']);
                if (isset($existingNames[$name])) {
                    throw new \RuntimeException('Existing items have equivalent Persian names; review duplicates before seeding.');
                }
                $existingNames[$name] = $existing['name'];
            }
            foreach ($items as $item) {
                $name = self::normalizedName($item['name']);
                if (isset($existingNames[$name]) && $existingNames[$name] !== $item['name']) {
                    throw new \RuntimeException('An existing item has an equivalent Persian name; review its spelling before seeding.');
                }
                $db->run('INSERT INTO kalaqpar_items(name,asset,can_fly) VALUES (?,?,?) ON CONFLICT(name) DO UPDATE SET asset=excluded.asset,can_fly=excluded.can_fly', [$item['name'], $item['asset'], $item['can_fly']]);
            }
        });
    }
    private static function normalizedName(string $name): string
    {
        $normalized = preg_replace('/[\s\p{Z}\p{Cf}\p{Mn}\x{0640}]+/u', '', strtr($name, ['ك' => 'ک', 'ي' => 'ی', 'ى' => 'ی']));
        if ($normalized === null) { throw new \RuntimeException('Invalid UTF-8 item name.'); }
        return $normalized;
    }
}
