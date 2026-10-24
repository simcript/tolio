<?php
namespace Src\Modules\Kalaqpar\Commands;

use Pano\Kernel\BaseCommand;
use Pano\Kernel\ResultCodeEnum;
use Src\Modules\Kalaqpar\Database\Migrator;
use Src\Modules\Kalaqpar\Database\Seeder;
use Src\Modules\Kalaqpar\Services\Factory;
use Src\Modules\Kalaqpar\Services\Settings;

final class ManageCommand extends BaseCommand
{
    public function handle(array $arguments): ResultCodeEnum
    {
        try {
            $f = new Factory();
            $command = $this->request->getSegments()[0];
            if ($command === 'kalaqpar:mail') {
                $sent = (new \Src\Modules\Kalaqpar\Services\Recovery($f->db, $f->settings))->dispatch();
                $this->info('Recovery dispatcher completed; SMTP accepted messages: ' . $sent);
                return ResultCodeEnum::OK;
            }
            if ($command === 'kalaqpar:stop') {
                if ($f->settings['progression'] === 'shared') {
                    $this->info('Shared mode has no daemon to stop. Use KQ_MAINTENANCE=true to pause mutations.');
                    return ResultCodeEnum::OK;
                }
                if (!$f->db->one('SELECT running FROM kalaqpar_worker_status WHERE id=1')['running']) {
                    $this->info('Worker is already stopped.');
                    return ResultCodeEnum::OK;
                }
                file_put_contents(dirname($f->db->path) . '/worker.stop', 'stop', LOCK_EX);
                $this->info('Graceful stop requested.');
                return ResultCodeEnum::OK;
            }
            if ($command === 'kalaqpar:backup') {
                $directory = dirname($f->db->path) . '/backups';
                if (!is_dir($directory)) { mkdir($directory, 0700, true); }
                $target = $directory . '/game-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sqlite';
                $f->db->pdo->exec('VACUUM INTO ' . $f->db->pdo->quote($target));
                @chmod($target, 0600);
                $this->info('SQLite snapshot created: ' . $target);
                return ResultCodeEnum::OK;
            }
            $lock = fopen(dirname($f->db->path) . '/worker.lock', 'c');
            if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new \RuntimeException('Another worker or maintenance command holds the lock.'); }
            try {
                match ($command) {
                    'kalaqpar:migrate' => (new Migrator())->migrate($f->db),
                    'kalaqpar:seed' => (new Seeder())->seed($f->db),
                    'kalaqpar:rebuild' => $f->engine()->rebuild(),
                    'kalaqpar:worker' => $this->worker($f),
                    default => throw new \RuntimeException('Unknown Kalaqpar command'),
                };
            } finally { flock($lock, LOCK_UN); fclose($lock); }
            $this->info($command . ' completed.');
            return ResultCodeEnum::OK;
        } catch (\Throwable $e) {
            $this->error('Kalaqpar: ' . $e->getMessage());
            return ResultCodeEnum::ERROR;
        }
    }
    private function worker(Factory $f): void
    {
        if ($f->settings['progression'] !== 'worker') { throw new \RuntimeException('Set KQ_PROGRESSION=worker to run a persistent worker; shared mode needs no daemon.'); }
        if ($f->settings['maintenance'] ?? false) { throw new \RuntimeException('Disable KQ_MAINTENANCE before starting the worker.'); }
        $content = $f->db->one('SELECT COUNT(*) n, SUM(can_fly) flying FROM kalaqpar_items WHERE enabled=1');
        if ($content['n'] < 100 || $content['flying'] < 30) { throw new \RuntimeException('Run kalaqpar:seed before starting the worker.'); }
        set_time_limit(0);
        $stopPath = dirname($f->db->path) . '/worker.stop';
        $running = true;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () use (&$running) { $running = false; });
            pcntl_signal(SIGINT, function () use (&$running) { $running = false; });
        }
        $log = function (string $event, array $fields = []) use ($f) {
            $line = json_encode(['at' => gmdate('c'), 'event' => $event] + $fields, JSON_UNESCAPED_SLASHES);
            if (@file_put_contents(dirname($f->db->path) . '/worker.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) { throw new \RuntimeException('Worker log unavailable'); }
            echo $line . PHP_EOL;
        };
        $engine = $f->engine();
        $log('worker_started', ['pid' => getmypid()]);
        try {
            while ($running && !is_file($stopPath)) {
                $start = Settings::now();
                try { $engine->tick(); }
                catch (\Throwable $e) { $log('tick_failed', ['type' => get_class($e)]); }
                usleep(max(1000, ($f->settings['tick_ms'] - (Settings::now() - $start)) * 1000));
            }
        } finally {
            $f->db->atomic(fn () => $f->db->run('UPDATE kalaqpar_worker_status SET running=0 WHERE id=1'));
            if (is_file($stopPath)) { unlink($stopPath); }
            $log('worker_stopped');
        }
    }
}
