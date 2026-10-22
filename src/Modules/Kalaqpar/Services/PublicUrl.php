<?php
namespace Src\Modules\Kalaqpar\Services;

// Pano 1.7.3's url() prefers HTTP_HOST. Invitations must use trusted config only.
final class PublicUrl
{
    public static function base(?array $settings = null): string
    {
        $base = $settings['public_base_url'] ?? config('kalaqpar.public_base_url', config('app.url', ''));
        if (!is_string($base) || !filter_var($base, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x20\x7f\\\\]/', $base)) {
            throw new \RuntimeException('Configure APP_URL or KQ_PUBLIC_BASE_URL as an absolute public HTTP(S) application URL.');
        }
        $parts = parse_url($base);
        $path = $parts['path'] ?? '';
        if (!in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host']) ||
            isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) ||
            preg_match('#//|%(?:2f|5c|2e)|(?:^|/)\.{1,2}(?:/|$)#i', $path)) {
            throw new \RuntimeException('Public application URL must have no credentials, query, fragment or ambiguous path.');
        }
        return rtrim($base, '/');
    }

    public static function mountPath(?array $settings = null): string
    {
        return rtrim(parse_url(self::base($settings), PHP_URL_PATH) ?? '', '/');
    }

    public static function path(?array $settings = null): string { return self::mountPath($settings) . '/kalaqpar'; }
    public static function room(string $code, ?array $settings = null): string
    {
        return self::base($settings) . '/kalaqpar/join/' . Invitation::normalize($code);
    }
}
