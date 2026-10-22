<?php
namespace Src\Modules\Kalaqpar\Handlers;

use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;
use Pano\Kernel\HttpStatusEnum;
use Src\Modules\Kalaqpar\Services\Factory;
use Src\Modules\Kalaqpar\Services\GameError;
use Src\Modules\Kalaqpar\Services\Progression;
use Src\Modules\Kalaqpar\Services\Settings;

final class ApiHandler extends BaseHandler
{
    private function body(): array
    {
        $body = $this->request->getData();
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096 || !is_array($body) || strlen(json_encode($body)) > 4096) {
            throw new GameError('body_size', 'درخواست بیش از حد بزرگ است.', 413);
        }
        return $body;
    }
    private function origin(): void
    {
        $headers = $this->request->getHeaders();
        $fetch = $headers['sec-fetch-site'] ?? '';
        if ($fetch !== '' && !in_array($fetch, ['same-origin','none'], true)) { throw new GameError('origin', 'مبدأ درخواست معتبر نیست.', 403); }
        if (isset($headers['origin'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : ($_SERVER['REQUEST_SCHEME'] ?? 'http');
            if ($headers['origin'] !== $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '')) { throw new GameError('origin', 'مبدأ درخواست معتبر نیست.', 403); }
        }
    }
    private function run(callable $work, bool $post = false, string $scope = '', int $limit = 30, bool $bootstrap = false, bool $anonymous = false, bool $invitationAccess = false): Response
    {
        try {
            if ($post) { $this->origin(); $body = $this->body(); } else { $body = []; }
            $factory = new Factory();
            if ($post && ($factory->settings['maintenance'] ?? false)) {
                throw new GameError('maintenance', 'سرویس بازی موقتاً برای نگهداری بسته است؛ کمی بعد برگرد.', 503);
            }
            $identity = $factory->identity();
            $u = null;
            if ($bootstrap) {
                $nonce = $this->request->getHeaders()['x-kq-boot'] ?? '';
                if (!is_string($nonce) || !isset($_COOKIE['kq_boot']) || !hash_equals($_COOKIE['kq_boot'], $nonce)) { throw new GameError('csrf', 'صفحه را تازه کن.', 403); }
                $identity->limit(hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local'), 'identity', 300);
                $u = $identity->bootstrap($nonce);
            } elseif (!$anonymous) {
                $u = $identity->resolve();
                if ($post && !hash_equals($u['csrf'], $this->request->getHeaders()['x-kq-csrf'] ?? '')) { throw new GameError('csrf', 'صفحه را تازه کن.', 403); }
            }
            if ($scope !== '') { $identity->limit($u ? 'u:' . $u['id'] : hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local'), $scope, $limit); }
            if ($invitationAccess) { $identity->limitInvitation($u); }
            if ($post && $u) {
                $identity->touch($u['id']);
                Progression::pulse($factory);
            }
            $result = $work($factory, $u, $body);
            return $this->json(['ok' => true, 'server_now' => Settings::now()] + ($result ?? []));
        } catch (GameError $e) {
            return $this->json(['ok' => false, 'error' => ['code' => $e->key, 'message' => $e->getMessage()]], $e->status);
        } catch (\Throwable $e) {
            error_log('kalaqpar error type=' . get_class($e));
            return $this->json(['ok' => false, 'error' => ['code' => 'service_unavailable', 'message' => 'سرویس بازی آماده نیست. کمی بعد دوباره تلاش کن.']], 503);
        }
    }
    private function json(array $data, int $status = 200): Response
    {
        return Response::json($data, HttpStatusEnum::from($status), ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff']);
    }
    private function roomId(array $body): int
    {
        $id = filter_var($body['room_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) { throw new GameError('room_id', 'شناسه اتاق معتبر نیست.', 422); }
        return $id;
    }
    private function page(): int { return max(1, min(10000, (int) ($this->request->getQueries()['page'] ?? 1))); }
    public function bootstrap(): Response { return $this->run(fn ($f, $u) => ['csrf' => $u['csrf'], 'profile' => $f->state()->profile($u['id']), 'state' => $f->state()->forUser($u['id'])], true, bootstrap: true); }
    public function heartbeat(): Response
    {
        return $this->run(fn ($f, $u, $b) => ['state' => $f->state()->forUser($u['id'], isset($b['match_id']) ? (int) $b['match_id'] : null)], true);
    }
    public function state(): Response { return $this->run(fn ($f, $u) => ['state' => $f->state()->forUser($u['id'], isset($this->request->getQueries()['match_id']) ? (int) $this->request->getQueries()['match_id'] : null)]); }
    public function profile(): Response { return $this->run(fn ($f, $u) => ['profile' => $f->state()->profile($u['id'], $this->page())]); }
    public function leaderboard(): Response
    {
        return $this->run(fn ($f, $u) => ['leaderboard' => $f->state()->leaderboard($u['id'], ($this->request->getQueries()['period'] ?? '') === 'all' ? 'all' : 'weekly', $this->page())]);
    }
    public function preview(): Response { return $this->run(fn ($f) => ['room' => $f->state()->preview($this->request->getQueries()['code'] ?? $this->request->getQueries()['token'] ?? '')], anonymous: true, invitationAccess: true); }
    public function health(): Response { return $this->run(fn ($f) => ['available' => $f->engine()->healthy(), 'progression_mode' => $f->settings['progression']], anonymous: true); }
    public function queue(): Response { return $this->run(function ($f, $u) { $f->engine()->queue($u['id']); return ['state' => $f->state()->forUser($u['id'])]; }, true, 'join', 30); }
    public function create(): Response { return $this->run(fn ($f, $u, $b) => $f->engine()->create($u['id'], $b), true, 'create', 6); }
    public function join(): Response { return $this->run(function ($f, $u, $b) { $f->engine()->join($u['id'], is_string($b['invitation'] ?? null) ? $b['invitation'] : ''); return []; }, true, 'join', 30, invitationAccess: true); }
    public function leave(): Response { return $this->run(function ($f, $u) { $f->engine()->leave($u['id']); return []; }, true, 'join', 30); }
    public function start(): Response { return $this->run(function ($f, $u, $b) { $f->engine()->start($u['id'], $this->roomId($b)); return []; }, true, 'start', 20); }
    public function answer(): Response { return $this->run(fn ($f, $u, $b) => $f->engine()->answer($u['id'], $b), true, 'answer', 180); }
    public function rematch(): Response { return $this->run(function ($f, $u, $b) { $f->engine()->rematch($u['id'], $this->roomId($b)); return []; }, true, 'join', 30); }
    public function spectate(): Response { return $this->run(function ($f, $u, $b) { $f->engine()->spectate($u['id'], $this->roomId($b)); return []; }, true, 'join', 30); }
    public function resume(): Response { return $this->run(function ($f, $u) { $f->engine()->returnToMatch($u['id']); return []; }, true, 'join', 30); }
    public function rename(): Response
    {
        return $this->run(function ($f, $u, $b) {
            $name = Settings::name($b['name'] ?? null);
            $avatar = filter_var($b['avatar'] ?? $u['avatar'], FILTER_VALIDATE_INT);
            if ($avatar === false || $avatar < 0 || $avatar > 5) { throw new GameError('avatar', 'رنگ آواتار معتبر نیست.', 422); }
            $f->db->atomic(fn () => $f->db->run('UPDATE kalaqpar_users SET name=?,avatar=? WHERE id=?', [$name, $avatar, $u['id']]));
            return ['profile' => $f->state()->profile($u['id'])];
        }, true, 'rename', 10);
    }
}
