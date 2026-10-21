<?php
namespace Src\Modules\Kalaqpar\Services;

final class Settings
{
    public static function values(): array { return config('kalaqpar', []) ?: require dirname(__DIR__) . '/Files/config.php'; }
    public static function now(): int { return (int) floor(microtime(true) * 1000); }
    public static function name(mixed $name, int $max = 30): string
    {
        if (!is_string($name) || !preg_match('//u', $name) || preg_match('/[\p{Cc}\p{Cs}\p{Co}<>]|(?![\x{200C}\x{200D}])\p{Cf}/u', $name)) {
            throw new GameError('invalid_name', 'نام معتبر فارسی یا لاتین وارد کن.', 422);
        }
        $name = trim(preg_replace('/[\p{Z}\s]+/u', ' ', $name));
        preg_match_all('/\X/u', $name, $characters);
        $visible = array_filter($characters[0], static fn ($c) => preg_match('/[\p{L}\p{N}\p{S}\p{P}]/u', $c));
        if (count($visible) < 2 || count($characters[0]) > $max) {
            throw new GameError('invalid_name', "نام باید بین ۲ و {$max} نویسه باشد.", 422);
        }
        return $name;
    }
}
