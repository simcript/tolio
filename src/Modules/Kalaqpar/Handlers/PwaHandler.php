<?php
namespace Src\Modules\Kalaqpar\Handlers;

use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;
use Pano\Kernel\HttpStatusEnum;
use Src\Modules\Kalaqpar\Services\PublicUrl;
use Src\Modules\Kalaqpar\Services\Pwa;

// Public endpoints: no Factory, database, identity, cookies or bootstrap tokens.
final class PwaHandler extends BaseHandler
{
    private function response(string $body, string $type, array $headers = []): Response
    {
        if (!Pwa::secure()) {
            return Response::text('PWA requires HTTPS (localhost is allowed).', HttpStatusEnum::FORBIDDEN, ['Cache-Control' => 'no-store']);
        }
        return Response::make($body, headers: $headers + [
            'Content-Type' => $type, 'Cache-Control' => 'no-cache, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function manifest(): Response
    {
        $icons = [];
        foreach (['icon-192.png' => '192x192', 'icon-512.png' => '512x512', 'icon-maskable-512.png' => '512x512'] as $file => $sizes) {
            $icons[] = ['src' => Pwa::asset($file), 'sizes' => $sizes, 'type' => 'image/png',
                'purpose' => str_contains($file, 'maskable') ? 'maskable' : 'any'];
        }
        return $this->response(json_encode([
            'id' => PublicUrl::local(url('', 'kalaqpar.tolio.ir')), 'name' => 'کلاغ‌پر؛ بازی گروهی', 'short_name' => 'کلاغ‌پر',
            'lang' => 'fa', 'dir' => 'rtl', 'display' => 'standalone',
            'start_url' => PublicUrl::local(url('', 'kalaqpar.tolio.ir')), 'scope' => PublicUrl::local(url('', 'kalaqpar.tolio.ir')),
            'theme_color' => '#f8f5ed', 'background_color' => '#f8f5ed',
            'description' => 'بازی گروهی آنلاین کلاغ‌پر؛ مسابقه به اینترنت نیاز دارد.', 'icons' => $icons,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'application/manifest+json; charset=utf-8');
    }

    public function worker(): Response
    {
        $base = PublicUrl::path();
        $prefix = 'kalaqpar-pwa-' . substr(hash('sha256', $base), 0, 16) . '-';
        $offline = PublicUrl::local(url('offline?v=' . Pwa::release(), 'kalaqpar.tolio.ir'));
        $config = json_encode(['base' => $base, 'prefix' => $prefix, 'cache' => $prefix . Pwa::release(),
            'offline' => $offline, 'assets' => array_map(Pwa::asset(...), array_keys(Pwa::ASSETS))], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $script = str_replace('/* KQ_CONFIG */', 'const CONFIG = ' . $config . ';', file_get_contents(dirname(__DIR__) . '/Assets/service-worker.js'));
        return $this->response($script, 'application/javascript; charset=utf-8', ['Service-Worker-Allowed' => PublicUrl::local(url('', 'kalaqpar.tolio.ir'))]);
    }

    public function offline(): Response
    {
        $version = $this->request->getQueries()['v'] ?? null;
        if ($version !== null && (!is_string($version) || !hash_equals(Pwa::release(), $version))) {
            return Response::text('Offline page version expired.', HttpStatusEnum::CONFLICT, ['Cache-Control' => 'no-store']);
        }
        return $this->response($this->module->view()->with(['base_path' => PublicUrl::path()])->render('offline'), 'text/html; charset=utf-8', [
            'X-KQ-Public-Asset' => '1',
            'Content-Security-Policy' => "default-src 'none'; style-src 'self'; img-src 'self'; font-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
        ]);
    }
}
