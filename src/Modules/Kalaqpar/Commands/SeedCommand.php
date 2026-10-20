<?php

namespace Src\Modules\Kalaqpar\Commands;

use Pano\Kernel\BaseCommand;
use Pano\Kernel\ResultCodeEnum;
use Src\Modules\Kalaqpar\KalaqparFactory;

/**
 * php pano kalaqpar kalaqpar:seed — loads the module word pack into the
 * kalaqpar_words table. Idempotent and scoped to that one table.
 */
final class SeedCommand extends BaseCommand
{
    public function handle(array $arguments): ResultCodeEnum
    {
        try {
            $factory = new KalaqparFactory();
            $connection = $factory->connection($this->module->path());
            $result = $factory->seeder()->seed($connection);
        } catch (\Throwable $error) {
            $this->error('kalaqpar:seed failed: ' . $error->getMessage());
            return ResultCodeEnum::ERROR;
        }

        $this->info(sprintf(
            'Kalaqpar word pack seeded: %d new, %d total entries.',
            $result['inserted'],
            $result['total']
        ));

        return ResultCodeEnum::OK;
    }
}
