<?php

namespace Src\Modules\Kalaqpar\Commands;

use Pano\Kernel\BaseCommand;
use Pano\Kernel\ResultCodeEnum;
use Src\Modules\Kalaqpar\KalaqparFactory;

/**
 * php pano kalaqpar kalaqpar:migrate — creates/updates the module schema
 * (idempotent, safe to re-run).
 */
final class MigrateCommand extends BaseCommand
{
    public function handle(array $arguments): ResultCodeEnum
    {
        try {
            $factory = new KalaqparFactory();
            $connection = $factory->connection($this->module->path());
            $applied = $factory->migrator()->migrate($connection);
        } catch (\Throwable $error) {
            $this->error('kalaqpar:migrate failed: ' . $error->getMessage());
            return ResultCodeEnum::ERROR;
        }

        if ($applied === []) {
            $this->info('Kalaqpar schema is already up to date.');
        } else {
            $this->info('Kalaqpar migrations applied: ' . implode(', ', $applied));
        }

        return ResultCodeEnum::OK;
    }
}
