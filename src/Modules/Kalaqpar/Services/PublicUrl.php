<?php
namespace Src\Modules\Kalaqpar\Services;

// Keep deployment prefixes and trusted public origins around Pano-generated routes.
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

    // url() returns an unescaped absolute URL without the configured mount path.
    // For Kalaqpar's PATH resolver, discard exactly the helper's origin.
    // This also handles CLI fallback when app.url contains a full URL.
    public static function local(string $url, ?array $settings = null): string
    {
//        $origin = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? config('app.url'));
//        return self::mountPath($settings) . substr($url, strlen($origin));
        return $url;
    }

    public static function absolute(string $url, ?array $settings = null): string
    {
        return self::base($settings) . substr(self::local($url, $settings), strlen(self::mountPath($settings)));
    }

    public static function path(?array $settings = null): string
    {
        return rtrim(self::local(url('', 'kalaqpar.tolio.ir'), $settings), '/');
    }
    public static function room(string $code, ?array $settings = null): string
    {
        return url('join/' . Invitation::normalize($code), 'kalaqpar.tolio.ir');
    }
}
