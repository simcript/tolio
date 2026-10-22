<?php
namespace Src\Modules\Kalaqpar\Services;

final class Settings
{
    public static function values(): array
    {
        $values = config('kalaqpar', []) ?: require dirname(__DIR__) . '/Files/config.php';
        $values['progression'] ??= 'shared';
        if (!in_array($values['progression'], ['shared', 'worker'], true)) {
            throw new \RuntimeException('KQ_PROGRESSION must be shared or worker.');
        }
        $values['maintenance'] = filter_var($values['maintenance'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($values['maintenance'] === null) { throw new \RuntimeException('KQ_MAINTENANCE must be true or false.'); }
        return $values;
    }
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
