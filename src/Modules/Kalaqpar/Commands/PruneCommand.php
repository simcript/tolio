<?php

namespace Src\Modules\Kalaqpar\Commands;

use Pano\Kernel\BaseCommand;
use Pano\Kernel\ResultCodeEnum;
use Src\Modules\Kalaqpar\KalaqparFactory;

/**
 * php pano kalaqpar kalaqpar:prune — retention cleanup for the module
 * database: completed rooms with their rounds/guesses/ledger entries/badge
 * rows, dated events, and stale rate-limit counters. Scoped strictly to
 * kalaqpar_* tables.
 *
 * Options: --days=N (default: kalaqpar.retention_days)
 */
final class PruneCommand extends BaseCommand
{
    public function handle(array $arguments): ResultCodeEnum
    {
        $days = max(1, (int) ($arguments['days'] ?? config('kalaqpar.retention_days', 14)));
        $cutoff = time() - ($days * 86400);

        try {
            $factory = new KalaqparFactory();
            $connection = $factory->connection($this->module->path());
        } catch (\Throwable $error) {
            $this->error('kalaqpar:prune could not open the module database: ' . $error->getMessage());
            return ResultCodeEnum::ERROR;
        }

        $counts = $connection->transaction(function () use ($connection, $cutoff): array {
            $rooms = $connection->all(
                'SELECT id FROM kalaqpar_rooms WHERE status = "closed" AND closed_at IS NOT NULL AND closed_at < ?',
                [$cutoff]
            );

            $roomCount = 0;

            foreach ($rooms as $room) {
                $roomId = (int) $room['id'];

                foreach ([
                    'DELETE FROM kalaqpar_events WHERE room_id = ?',
                    'DELETE FROM kalaqpar_scores WHERE room_id = ?',
                    'DELETE FROM kalaqpar_guesses WHERE round_id IN (SELECT id FROM kalaqpar_rounds WHERE room_id = ?)',
                    'DELETE FROM kalaqpar_round_players WHERE round_id IN (SELECT id FROM kalaqpar_rounds WHERE room_id = ?)',
                    'DELETE FROM kalaqpar_rounds WHERE room_id = ?',
                    'DELETE FROM kalaqpar_room_players WHERE room_id = ?',
                    'DELETE FROM kalaqpar_rooms WHERE id = ?',
                ] as $sql) {
                    $connection->run($sql, [$roomId]);
                }

                $roomCount++;
            }

            $connection->run('DELETE FROM kalaqpar_events WHERE created_at < ?', [$cutoff]);

            $connection->run(
                'DELETE FROM kalaqpar_rate_limits WHERE window_started_at < ?',
                [time() - 3600]
            );

            return ['rooms' => $roomCount];
        });

        $this->info(sprintf(
            'Kalaqpar pruned %d rooms (and their rounds/events/ledger rows) older than %d days.',
            $counts['rooms'],
            $days
        ));

        return ResultCodeEnum::OK;
    }
}
