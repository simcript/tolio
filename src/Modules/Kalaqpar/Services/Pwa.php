<?php
namespace Src\Modules\Kalaqpar\Services;

// Only these public files may enter the PWA cache. Never enumerate API data.
final class Pwa
{
    public const ASSETS = [
        'bootstrap.rtl.min.css' => 'text/css', 'style.css' => 'text/css',
        'app.js' => 'application/javascript', 'pwa.js' => 'application/javascript',
        'account.js' => 'application/javascript',
        'Vazirmatn-Regular.woff2' => 'font/woff2', 'hero.svg' => 'image/svg+xml',
        'icon-192.png' => 'image/png', 'icon-512.png' => 'image/png',
        'icon-maskable-512.png' => 'image/png', 'apple-touch-icon.png' => 'image/png',
    ];

    public static function asset(string $file): string
    {
        if (!isset(self::ASSETS[$file])) { throw new \InvalidArgumentException('Unknown PWA asset.'); }
        return PublicUrl::path() . '/assets/' . $file . '?v=' . hash_file('sha256', dirname(__DIR__) . '/Assets/' . $file);
    }

    public static function release(): string
    {
        $parts = array_map(self::asset(...), array_keys(self::ASSETS));
        foreach (['Assets/service-worker.js', 'Views/offline.php', 'Services/Pwa.php', 'Handlers/PwaHandler.php'] as $file) {
            $parts[] = hash_file('sha256', dirname(__DIR__) . '/' . $file);
        }
        return hash('sha256', implode('|', $parts));
    }

    public static function secure(): bool
    {
        $url = parse_url(PublicUrl::base());
        return $url['scheme'] === 'https' || in_array($url['host'], ['localhost', '127.0.0.1', '[::1]'], true);
    }
}
