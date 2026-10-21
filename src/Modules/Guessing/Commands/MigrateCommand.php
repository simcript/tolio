<?php

namespace Src\Modules\Guessing\Commands;

use Pano\Kernel\BaseCommand;
use Pano\Kernel\ResultCodeEnum;
use Src\Modules\Guessing\GuessingFactory;

/**
 * php pano guessing guessing:migrate — creates/updates the module schema
 * (idempotent, safe to re-run).
 */
final class MigrateCommand extends BaseCommand
{
    public function handle(array $arguments): ResultCodeEnum
    {
        try {
            $factory = new GuessingFactory();
            $connection = $factory->connection($this->module->path());
            $applied = $factory->migrator()->migrate($connection);
        } catch (\Throwable $error) {
            $this->error('guessing:migrate failed: ' . $error->getMessage());
            return ResultCodeEnum::ERROR;
        }

        if ($applied === []) {
            $this->info('Guessing schema is already up to date.');
        } else {
            $this->info('Guessing migrations applied: ' . implode(', ', $applied));
        }

        return ResultCodeEnum::OK;
    }
}
