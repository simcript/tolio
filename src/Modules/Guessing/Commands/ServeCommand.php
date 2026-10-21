<?php

namespace Src\Modules\Guessing\Commands;

use Pano\Kernel\BaseCommand;
use Pano\Kernel\ResultCodeEnum;
use Src\Modules\Guessing\GuessingFactory;
use Src\Modules\Guessing\Services\GameEngine;

/**
 * php pano guessing guessing:serve — the persistent game worker. Each pulse
 * advances reveals/rounds/retirement through GameEngine->tick() and writes
 * a small health file used by the /guessing/health endpoint.
 *
 * Options:
 *   --interval=SECONDS   pause between pulses (default: guessing.worker_interval)
 *   --max-ticks=N        stop after N pulses (for smoke runs/tests)
 *
 * The framework folds a single --option into the command URL segment, so the
 * route registers an optional trailing `[opts?]` segment. For zero-option
 * bounded runs (scripts/CI) set GUESSING_WORKER_TICKS; pass no option for an
 * always-on worker.
 *
 * Multiple worker instances are safe: every world transition is a
 * precondition-checked BEGIN IMMEDIATE transaction.
 */
final class ServeCommand extends BaseCommand
{
    public function handle(array $arguments): ResultCodeEnum
    {
        $interval = max(1, (int) ($arguments['interval'] ?? config('guessing.worker_interval', 1)));
        $configuredTicks = (int) (config('guessing.worker_ticks', 0) ?? 0);
        $maxTicks = isset($arguments['max-ticks'])
            ? max(1, (int) $arguments['max-ticks'])
            : ($configuredTicks > 0 ? $configuredTicks : null);

        set_time_limit(0);

        try {
            $factory = new GuessingFactory();
            $graph = $factory->services($this->module->path());
        } catch (\Throwable $error) {
            $this->error('guessing:serve could not open the module database: ' . $error->getMessage());
            return ResultCodeEnum::ERROR;
        }

        /** @var GameEngine $engine */
        $engine = $graph['engine'];
        $healthPath = $this->module->path('Files') . DIRECTORY_SEPARATOR . 'worker-health.json';

        $this->info("Guessing worker running (interval {$interval}s"
            . ($maxTicks !== null ? ", max {$maxTicks} ticks" : '') . "). Press Ctrl+C to stop.");

        $ticks = 0;
        $startedAt = time();

        while ($maxTicks === null || $ticks < $maxTicks) {
            $tickAt = time();

            try {
                $graph['connection']->transaction(function () use ($engine, $tickAt, &$summary) {
                    $summary = $engine->tick($tickAt);
                });
            } catch (\Throwable $error) {
                // A single failed pulse never kills the worker; it logs and
                // keeps the loop alive so transient SQLite contention just
                // delays the next advance.
                $this->error('guessing serve pulse failed: ' . $error->getMessage());
                $summary = null;
            }

            $this->writeHealth($healthPath, $startedAt, $tickAt, $summary);
            $ticks++;
            sleep($interval);
        }

        $this->info("Guessing worker stopped after {$ticks} pulses.");
        return ResultCodeEnum::OK;
    }

    /** @param array{reveals: int, finished_rounds: int, started_rounds: int, closed_rooms: int}|null $summary */
    private function writeHealth(string $path, int $startedAt, int $tickAt, ?array $summary): void
    {
        try {
            file_put_contents($path, json_encode([
                'pid' => getmypid(),
                'started_at' => $startedAt,
                'last_tick_at' => $tickAt,
                'last_summary' => $summary ?? [],
            ], JSON_UNESCAPED_UNICODE), LOCK_EX);
        } catch (\Throwable) {
            // Health reporting must never break the worker.
        }
    }
}
