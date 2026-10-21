<?php
namespace Src\Modules\Kalaqpar\Handlers;

use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;
use Pano\Kernel\HttpStatusEnum;
use Src\Modules\Kalaqpar\Services\Identity;

final class DefaultHandler extends BaseHandler
{
    public function invitation(string $token): Response { return $this->page($token); }
    public function home(): Response { return $this->page(''); }
    private function page(string $token): Response
    {
        $nonce = $_COOKIE['kq_boot'] ?? '';
        if (!is_string($nonce) || !preg_match('/^[a-f0-9]{64}$/D', $nonce)) {
            $nonce = bin2hex(random_bytes(32));
            Identity::cookie('kq_boot', $nonce, time() + 86400);
        }
        return Response::html($this->module->view()->with(['nonce' => $nonce, 'invitation' => $token])->layout('layout')->render('home'), headers: [
            'Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
        ]);
    }
    public function asset(?string $file = null): Response
    {
        $file ??= '';
        $types = ['app.js' => 'application/javascript', 'style.css' => 'text/css', 'bootstrap.rtl.min.css' => 'text/css',
            'Vazirmatn-Regular.woff2' => 'font/woff2', 'hero.svg' => 'image/svg+xml'];
        if (isset($types[$file])) { $path = dirname(__DIR__) . '/Assets/' . $file; $type = $types[$file]; }
        elseif (preg_match('#^items/([a-f0-9]{24})\.svg$#D', $file, $m)) { $path = dirname(__DIR__) . '/Files/items/' . $m[1] . '.svg'; $type = 'image/svg+xml'; }
        else { return Response::text('Not found', HttpStatusEnum::NOT_FOUND); }
        if (!is_file($path)) { return Response::text('Not found', HttpStatusEnum::NOT_FOUND); }
        $bytes = @file_get_contents($path);
        if ($bytes === false) { return Response::text('Asset unavailable', HttpStatusEnum::SERVICE_UNAVAILABLE); }
        return Response::make($bytes, headers: ['Content-Type' => $type, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => str_starts_with($file, 'items/') ? 'public, max-age=31536000, immutable' : 'public, max-age=3600']);
    }
}
