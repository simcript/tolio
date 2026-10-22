<?php
namespace Src\Modules\Kalaqpar\Services;

// Runs one engine pass, never a background loop or a client-timed transition.
final class Progression
{
    public static function pulse(Factory $factory): void
    {
        if ($factory->settings['maintenance'] ?? false) {
            throw new GameError('maintenance', 'سرویس بازی موقتاً برای نگهداری بسته است؛ کمی بعد برگرد.', 503);
        }
        if (($factory->settings['progression'] ?? 'shared') !== 'shared') { return; }
        $lock = @fopen(dirname($factory->db->path) . '/worker.lock', 'c');
        if (!$lock) { throw new \RuntimeException('Progression lock unavailable'); }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                $status = $factory->db->one('SELECT heartbeat FROM kalaqpar_worker_status WHERE id=1');
                if (!$status || $status['heartbeat'] < Settings::now() - $factory->settings['health_ms']) {
                    throw new GameError('progression_busy', 'سرویس بازی در حال نگهداری یا پردازش است؛ کمی بعد دوباره تلاش کن.', 503);
                }
                return; // Another short request is already progressing the same database.
            }
            $status = $factory->db->one('SELECT heartbeat FROM kalaqpar_worker_status WHERE id=1');
            if ($status && $status['heartbeat'] >= Settings::now() - $factory->settings['shared_tick_ms']) { return; }
            $engine = $factory->engine();
            if (!$engine->healthy()) {
                throw new GameError('worker_unavailable', 'محتوای بازی آماده نیست؛ مدیر باید مهاجرت و seed را اجرا کند.', 503);
            }
            $engine->tick();
        } finally {
            // Closing also releases a lock when an exception interrupts a pass.
            fclose($lock);
        }
    }
}
