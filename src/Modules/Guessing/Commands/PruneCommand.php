<?php

namespace Src\Modules\Guessing\Commands;

use Pano\Kernel\BaseCommand;
use Pano\Kernel\ResultCodeEnum;
use Src\Modules\Guessing\GuessingFactory;

/**
 * php pano guessing guessing:prune — retention cleanup for the module
 * database: completed rooms with their rounds/guesses/ledger entries/badge
 * rows, dated events, and stale rate-limit counters. Scoped strictly to
 * guessing_* tables.
 *
 * Options: --days=N (default: guessing.retention_days)
 */
final class PruneCommand extends BaseCommand
{
    public function handle(array $arguments): ResultCodeEnum
    {
        $days = max(1, (int) ($arguments['days'] ?? config('guessing.retention_days', 14)));
        $cutoff = time() - ($days * 86400);

        try {
            $factory = new GuessingFactory();
            $connection = $factory->connection($this->module->path());
        } catch (\Throwable $error) {
            $this->error('guessing:prune could not open the module database: ' . $error->getMessage());
            return ResultCodeEnum::ERROR;
        }

        $counts = $connection->transaction(function () use ($connection, $cutoff): array {
            $rooms = $connection->all(
                'SELECT id FROM guessing_rooms WHERE status = "closed" AND closed_at IS NOT NULL AND closed_at < ?',
                [$cutoff]
            );

            $roomCount = 0;

            foreach ($rooms as $room) {
                $roomId = (int) $room['id'];

                foreach ([
                    'DELETE FROM guessing_events WHERE room_id = ?',
                    'DELETE FROM guessing_scores WHERE room_id = ?',
                    'DELETE FROM guessing_guesses WHERE round_id IN (SELECT id FROM guessing_rounds WHERE room_id = ?)',
                    'DELETE FROM guessing_round_players WHERE round_id IN (SELECT id FROM guessing_rounds WHERE room_id = ?)',
                    'DELETE FROM guessing_rounds WHERE room_id = ?',
                    'DELETE FROM guessing_room_players WHERE room_id = ?',
                    'DELETE FROM guessing_rooms WHERE id = ?',
                ] as $sql) {
                    $connection->run($sql, [$roomId]);
                }

                $roomCount++;
            }

            $connection->run('DELETE FROM guessing_events WHERE created_at < ?', [$cutoff]);

            $connection->run(
                'DELETE FROM guessing_rate_limits WHERE window_started_at < ?',
                [time() - 3600]
            );

            return ['rooms' => $roomCount];
        });

        $this->info(sprintf(
            'Guessing pruned %d rooms (and their rounds/events/ledger rows) older than %d days.',
            $counts['rooms'],
            $days
        ));

        return ResultCodeEnum::OK;
    }
}
