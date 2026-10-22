<?php
namespace Src\Modules\Kalaqpar\Http;

use Src\Modules\Kalaqpar\Services\PublicUrl;

// Pano 1.7.3 does not strip an application mount path before module resolution.
// Normalize only Kalaqpar paths; keep every other host module's request intact.
final class Request extends \Pano\Foundation\Request
{
    public function __construct(array $data, ?string $module = null)
    {
        $configured = config('kalaqpar.public_base_url', config('app.url', ''));
        $prefix = is_string($configured) ? rtrim(parse_url($configured, PHP_URL_PATH) ?: '', '/') : '';
        $uri = $data['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '';
        if ($prefix === '' || ($path !== $prefix . '/kalaqpar' && !str_starts_with($path, $prefix . '/kalaqpar/'))) {
            parent::__construct($data, $module);
            return;
        }
        PublicUrl::base(); // Validate the configured prefix before using it.
        $original = $_SERVER['REQUEST_URI'] ?? null;
        $data['REQUEST_URI'] = substr($uri, strlen($prefix));
        $_SERVER['REQUEST_URI'] = $data['REQUEST_URI'];
        try { parent::__construct($data, $module); }
        finally {
            if ($original === null) { unset($_SERVER['REQUEST_URI']); }
            else { $_SERVER['REQUEST_URI'] = $original; }
        }
    }
}
