<?php

namespace Src\Modules\Kalaqpar\Database;

use RuntimeException;
use Src\Modules\Kalaqpar\Services\GameRules;

/**
 * Seeds the module word pack. Idempotent and scoped to the kalaqpar_words
 * table: only words missing from the database are inserted, never removed,
 * so operator-added words survive re-seeding and unrelated data is untouched.
 */
final class WordSeeder
{
    public function __construct(private readonly string $wordsFile)
    {
    }

    /** @return array{inserted: int, total: int} */
    public function seed(Connection $connection): array
    {
        $words = $this->words();

        $inserted = $connection->transaction(function () use ($connection, $words): int {
            $insert = 0;

            foreach ($words as $word) {
                $normalized = GameRules::normalize($word);
                if ($normalized === '' || mb_strlen($normalized, 'UTF-8') < 2) {
                    continue;
                }

                $existing = $connection->run(
                    'SELECT word FROM kalaqpar_words WHERE word = ?',
                    [$normalized]
                )->fetchColumn();

                if ($existing === false) {
                    $connection->run(
                        'INSERT INTO kalaqpar_words (word) VALUES (?)',
                        [$normalized]
                    );
                    $insert++;
                }
            }

            return $insert;
        });

        return [
            'inserted' => $inserted,
            'total' => count($words),
        ];
    }

    /** @return list<string> */
    public function words(): array
    {
        if (!is_file($this->wordsFile)) {
            throw new RuntimeException("Kalaqpar word pack not found ($this->wordsFile).");
        }

        $decoded = json_decode((string) file_get_contents($this->wordsFile), true);

        if (!is_array($decoded) || !is_array($decoded['words'] ?? null)) {
            throw new RuntimeException('Kalaqpar word pack is not a valid words dictionary.');
        }

        return array_values(array_filter(
            array_map(static fn ($word): string => trim((string) $word), $decoded['words']),
            static fn (string $word): bool => $word !== ''
        ));
    }
}
