<?php
namespace Src\Modules\Kalaqpar\Handlers;

use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;
use Pano\Kernel\HttpStatusEnum;
use Src\Modules\Kalaqpar\Services\{Accounts, Factory, GameError, Identity, PublicUrl, Recovery, Settings};

final class AccountHandler extends BaseHandler
{
    // Preserve form POST origins while keeping reset tokens out of referrer URLs.
    private const HEADERS = [
        'Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache', 'Vary' => 'Cookie',
        'Referrer-Policy' => 'strict-origin', 'X-Content-Type-Options' => 'nosniff',
        'Content-Security-Policy' => "default-src 'none'; script-src 'self'; connect-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
    ];
    public function register(): Response { return $this->run('register'); }
    public function registerPost(): Response { return $this->run('register', true); }
    public function login(): Response { return $this->run('login'); }
    public function loginPost(): Response { return $this->run('login', true); }
    public function forgot(): Response { return $this->run('forgot-password'); }
    public function forgotPost(): Response { return $this->run('forgot-password', true); }
    public function reset(): Response { return $this->run('reset-password'); }
    public function resetPost(): Response { return $this->run('reset-password', true); }
    public function account(): Response { return $this->run('account'); }
    public function rename(): Response { return $this->run('name', true); }
    public function logout(): Response { return $this->run('logout', true); }
    private function redirect(string $path): Response
    {
        return Response::redirect(PublicUrl::local(url($path, 'kalaqpar.tolio.ir')), HttpStatusEnum::SEE_OTHER, self::HEADERS);
    }
    private function nonce(): string
    {
        $nonce = $_COOKIE['kq_account_csrf'] ?? '';
        if (!is_string($nonce) || !preg_match('/^[a-f0-9]{64}$/D', $nonce)) {
            $nonce = bin2hex(random_bytes(32));
            Identity::cookie('kq_account_csrf', $nonce, time() + 7200);
            $_COOKIE['kq_account_csrf'] = $nonce;
        }
        return $nonce;
    }
    private function checkPost(Factory $f, array $body): void
    {
        $headers = $this->request->getHeaders();
        $site = $headers['sec-fetch-site'] ?? '';
        // Match the receiving origin, as ApiHandler does; APP_URL may belong to another module.
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : ($_SERVER['REQUEST_SCHEME'] ?? 'http');
        $origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
        if (($site !== '' && !in_array($site, ['same-origin', 'none'], true)) ||
            (isset($headers['origin']) && $headers['origin'] !== $origin)) {
            throw new GameError('csrf', 'مبدأ درخواست معتبر نیست؛ صفحه را تازه کنید.', 403);
        }
        $nonce = $_COOKIE['kq_account_csrf'] ?? '';
        if (!is_string($nonce) || !preg_match('/^[a-f0-9]{64}$/D', $nonce) || !is_string($body['csrf'] ?? null) ||
            !hash_equals($f->identity()->formCsrf($nonce), $body['csrf'])) {
            throw new GameError('csrf', 'صفحه منقضی شده یا هویت تغییر کرده است؛ صفحه را تازه کنید.', 403);
        }
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096 || strlen(json_encode($body)) > 4096) {
            throw new GameError('body', 'درخواست بیش از حد بزرگ است.', 413);
        }
        if ($f->settings['maintenance'] ?? false) { throw new GameError('maintenance', 'سرویس موقتاً برای نگهداری بسته است.', 503); }
    }
    private function run(string $page, bool $post = false): Response
    {
        $queries = $this->request->getQueries();
        // Existing GET entry points now select a state of the same profile. POST URLs stay compatible.
        if (!$post && in_array($page, ['register', 'login', 'forgot-password'], true)) {
            return $this->redirect('/account?view=' . $page . ($page === 'login' && ($queries['reset'] ?? '') === '1' ? '&reset=1' : '') . '#account');
        }
        if ($page === 'account' && in_array($queries['view'] ?? '', ['register', 'login', 'forgot-password', 'logout'], true)) {
            $page = $queries['view'];
        }
        $status = 200; $error = ''; $message = ''; $email = ''; $csrf = ''; $user = null; $credential = null; $registered = false;
        $available = false; $token = ''; $validToken = false; $errorKey = ''; $profile = null; $blocked = false;
        $historyPage = max(1, min(10000, (int) ($queries['history'] ?? 1)));
        try {
            $f = new Factory();
            $accounts = new Accounts($f->db, $f->settings);
            $recovery = new Recovery($f->db, $f->settings);
            $identity = $f->identity();
            if ($post) { $body = $this->request->getData(); $this->checkPost($f, $body); } else { $body = []; }
            // Profile states share the ordinary guest identity; reset links never bootstrap one.
            try { $user = $identity->resolve(); }
            catch (GameError $e) {
                if ($e->key !== 'identity_required') { throw $e; }
                if (!$post && $page !== 'reset-password') {
                    $identity->limit('ip:' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local'), 'account-guest', 60);
                    $nonce = bin2hex(random_bytes(32));
                    Identity::cookie('kq_boot', $nonce, time() + 86400);
                    $user = $identity->bootstrap($nonce);
                }
            }
            $credential = $user ? $accounts->credential($user['id']) : null;
            $registered = $user && $accounts->registered($user['id']);
            if ($user && $page !== 'reset-password') {
                $profile = $f->state()->profile($user['id'], $historyPage);
                try { $accounts->assertCanSwitch($user['id']); }
                catch (GameError $e) { if ($e->key !== 'engaged') { throw $e; } $blocked = true; }
            }
            $available = $recovery->available();
            $tokenInput = $post ? ($body['token'] ?? '') : ($this->request->getQueries()['token'] ?? '');
            $token = is_string($tokenInput) && preg_match('/^[a-f0-9]{64}$/D', $tokenInput) ? $tokenInput : '';
            $validToken = $page === 'reset-password' && (bool) $recovery->token($token);
            if ($post) {
                $email = is_string($body['email'] ?? null) ? substr(trim($body['email']), 0, 254) : '';
                if ($page === 'forgot-password') {
                    if (!$available) { throw new GameError('recovery_unavailable', 'بازیابی رمز فعلاً در دسترس نیست. کمی بعد دوباره تلاش کنید.', 503); }
                    $recovery->request($body['email'] ?? '');
                    $message = Recovery::RESPONSE;
                } elseif ($page === 'reset-password') {
                    $identity->limit('ip:' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local'), 'reset', 20, 900);
                    $recovery->reset($token, $body['password'] ?? null, $body['password_confirmation'] ?? null);
                    // Do not sign out an unrelated guest/registered identity on the resetting browser.
                    if ($user) {
                        try { $identity->resolve(); } catch (GameError) { $accounts->clearBrowser(); }
                    }
                    return $this->redirect('/account?view=login&reset=1#account');
                } else {
                    if (!$user) { throw new GameError('identity_required', 'برای ادامه صفحه بازی را باز کنید و دوباره برگردید.', 401); }
                    $identity->limit('ip:' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local'), 'account-' . $page, $page === 'login' ? 20 : 10, 900);
                    $identity->limit('u:' . $user['id'], 'account-' . $page, 10, 900);
                    if ($page === 'name') {
                        $name = Settings::name($body['name'] ?? null);
                        $avatar = filter_var($body['avatar'] ?? $user['avatar'], FILTER_VALIDATE_INT);
                        if ($avatar === false || $avatar < 0 || $avatar > 5) { throw new GameError('avatar', 'رنگ آواتار معتبر نیست.', 422); }
                        $f->db->atomic(function () use ($f, $identity, $name, $avatar) {
                            $current = $identity->resolve();
                            $f->db->run('UPDATE kalaqpar_users SET name=?,avatar=? WHERE id=?', [$name, $avatar, $current['id']]);
                        });
                        return $this->redirect('/account?named=1');
                    }
                    if ($page === 'register') {
                        $accounts->register($body['email'] ?? null, $body['password'] ?? null, $body['password_confirmation'] ?? null);
                        return $this->redirect('/account?registered=1#account');
                    }
                    if ($page === 'login') {
                        // Combine address and socket IP; attackers cannot lock an account globally.
                        try { $lookup = Accounts::email($body['email'] ?? ''); } catch (GameError) { $lookup = ''; }
                        $identity->limit(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'local') . ':' . $lookup), 'login-address-ip', 8, 900);
                        $accounts->login($body['email'] ?? null, $body['password'] ?? null);
                        return $this->redirect('/account#account');
                    }
                    if ($page === 'logout') { $accounts->logout(); return $this->redirect('/'); }
                }
            }
            if ($page === 'login' && ($this->request->getQueries()['reset'] ?? '') === '1') { $message = 'رمز عبور تغییر کرد. اکنون با رمز تازه وارد شوید.'; }
            if ($page === 'account' && $registered && ($queries['registered'] ?? '') === '1') { $message = 'ثبت‌نام انجام شد؛ همین حساب، شناسه، نام، امتیازها و تاریخچه‌ات حفظ شدند. ایمیل تأیید نشده است.'; }
            if (($queries['named'] ?? '') === '1') { $message = 'نام و آواتار ذخیره شدند.'; }
            $csrf = $identity->formCsrf($this->nonce());
        } catch (GameError $e) {
            $status = $e->status; $error = $e->getMessage(); $errorKey = $e->key;
            if ($e->key === 'engaged') { $blocked = true; }
            if ($e->key === 'reset') { $validToken = false; }
            if (isset($identity)) { $csrf = $identity->formCsrf($this->nonce()); }
        } catch (\Throwable $e) {
            error_log('kalaqpar account event=unavailable type=' . get_class($e));
            $status = 503; $error = 'سرویس حساب فعلاً آماده نیست؛ کمی بعد دوباره تلاش کنید.';
        }
        return Response::html($this->module->view()->with([
            'page' => $page, 'error' => $error, 'message' => $message, 'email' => $email, 'csrf' => $csrf,
            'user' => $user, 'credential' => $credential, 'registered' => $registered, 'recovery_available' => $available,
            'token' => $token, 'valid_token' => $validToken, 'base_path' => PublicUrl::path(),
            'profile' => $profile, 'blocked' => $blocked, 'error_key' => $errorKey, 'history_page' => $historyPage,
        ])->layout('account-layout')->render('account'), HttpStatusEnum::from($status), self::HEADERS);
    }
}
