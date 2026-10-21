<?php

namespace Src\Modules\Guessing\Commands;

use Pano\Kernel\BaseCommand;
use Pano\Kernel\ResultCodeEnum;
use Src\Modules\Guessing\GuessingFactory;

/**
 * php pano guessing guessing:seed — loads the module word pack into the
 * guessing_words table. Idempotent and scoped to that one table.
 */
final class SeedCommand extends BaseCommand
{
    public function handle(array $arguments): ResultCodeEnum
    {
        try {
            $factory = new GuessingFactory();
            $connection = $factory->connection($this->module->path());
            $result = $factory->seeder()->seed($connection);
        } catch (\Throwable $error) {
            $this->error('guessing:seed failed: ' . $error->getMessage());
            return ResultCodeEnum::ERROR;
        }

        $this->info(sprintf(
            'Guessing word pack seeded: %d new, %d total entries.',
            $result['inserted'],
            $result['total']
        ));

        return ResultCodeEnum::OK;
    }
}
