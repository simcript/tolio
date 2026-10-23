<?php
namespace Src\Modules\Kalaqpar\Handlers;

use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;
use Pano\Kernel\HttpStatusEnum;
use Src\Modules\Kalaqpar\Services\Identity;
use Src\Modules\Kalaqpar\Services\Factory;
use Src\Modules\Kalaqpar\Services\GameError;
use Src\Modules\Kalaqpar\Services\PublicUrl;
use Src\Modules\Kalaqpar\Services\Pwa;

final class DefaultHandler extends BaseHandler
{
    public function invitation(string $token): Response { return $this->page($token); }
    public function home(): Response
    {
        // Keep the launch page inside the strictly trailing-slash worker scope.
        if (!str_ends_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/')) {
            return Response::redirect(PublicUrl::path() . '/', HttpStatusEnum::TEMPORARY_REDIRECT, ['Cache-Control' => 'no-store']);
        }
        return $this->page('');
    }
    private function page(string $token): Response
    {
        $error = '';
        $status = HttpStatusEnum::OK;
        if ($token !== '') {
            try { (new Factory())->identity()->limitInvitation(); }
            catch (GameError $e) { $error = $e->getMessage(); $status = HttpStatusEnum::from($e->status); }
            catch (\Throwable) { $error = 'سرویس بازی آماده نیست؛ کمی بعد دوباره تلاش کن.'; $status = HttpStatusEnum::SERVICE_UNAVAILABLE; }
        }
        $nonce = $_COOKIE['kq_boot'] ?? '';
        if (!is_string($nonce) || !preg_match('/^[a-f0-9]{64}$/D', $nonce)) {
            $nonce = bin2hex(random_bytes(32));
            Identity::cookie('kq_boot', $nonce, time() + 86400);
        }
        return Response::html($this->module->view()->with(['nonce' => $nonce, 'invitation' => substr($token, 0, 160),
            'base_path' => PublicUrl::path(), 'initial_error' => $error])->layout('layout')->render('home'), $status, headers: [
            'Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; worker-src 'self'; manifest-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
        ]);
    }
    public function asset(?string $file = null): Response
    {
        $file ??= '';
        $types = Pwa::ASSETS;
        if (isset($types[$file])) { $path = dirname(__DIR__) . '/Assets/' . $file; $type = $types[$file]; }
        elseif (preg_match('#^items/([a-f0-9]{24})\.svg$#D', $file, $m)) { $path = dirname(__DIR__) . '/Files/items/' . $m[1] . '.svg'; $type = 'image/svg+xml'; }
        else { return Response::text('Not found', HttpStatusEnum::NOT_FOUND); }
        if (!is_file($path)) { return Response::text('Not found', HttpStatusEnum::NOT_FOUND); }
        $bytes = @file_get_contents($path);
        if ($bytes === false) { return Response::text('Asset unavailable', HttpStatusEnum::SERVICE_UNAVAILABLE); }
        $version = $this->request->getQueries()['v'] ?? null;
        if ($version !== null && (!is_string($version) || !hash_equals(hash('sha256', $bytes), $version))) {
            return Response::text('Asset version expired; close all game tabs and reopen.', HttpStatusEnum::CONFLICT, ['Cache-Control' => 'no-store']);
        }
        return Response::make($bytes, headers: ['Content-Type' => $type, 'X-Content-Type-Options' => 'nosniff', 'X-KQ-Public-Asset' => '1',
            'Cache-Control' => ($version !== null || str_starts_with($file, 'items/')) ? 'public, max-age=31536000, immutable' : 'no-cache']);
    }
}
