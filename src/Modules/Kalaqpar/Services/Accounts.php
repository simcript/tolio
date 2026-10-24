<?php
namespace Src\Modules\Kalaqpar\Services;

use Src\Modules\Kalaqpar\Database\Connection;

final class Accounts
{
    // ASCII mailbox syntax; compare the entire address case-insensitively. No provider rewrites.
    public static function email(mixed $value): string
    {
        if (!is_string($value)) { throw new GameError('email', 'ایمیل معتبر وارد کنید.', 422); }
        $value = trim($value);
        if (strlen($value) > 254 || preg_match('/[^\x21-\x7e]/', $value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new GameError('email', 'ایمیل معتبر با حروف لاتین وارد کنید.', 422);
        }
        return strtolower($value);
    }

    public static function password(mixed $password, mixed $confirmation): string
    {
        if (!is_string($password) || !is_string($confirmation) || $password !== $confirmation) {
            throw new GameError('password', 'رمز عبور و تکرار آن یکسان نیستند.', 422);
        }
        if (!preg_match('//u', $password) || str_contains($password, "\0") ||
            preg_match_all('/./us', $password) < 12 || strlen($password) > 72) {
            throw new GameError('password', 'رمز باید حداقل ۱۲ نویسه و حداکثر ۷۲ بایت UTF-8 باشد (برای فارسی معمولاً حداکثر ۳۶ حرف). فاصله‌ها حذف نمی‌شوند.', 422);
        }
        return $password;
    }

    public function __construct(private Connection $db, private array $settings) {}
    public function credential(int $id): ?array { return $this->db->one('SELECT * FROM kalaqpar_email_accounts WHERE user_id=?', [$id]); }

    public function registered(int $id): bool
    {
        if ($this->credential($id)) { return true; }
        if (!$this->db->one("SELECT 1 FROM sqlite_master WHERE type='table' AND name='kalaqpar_accounts'")) { return false; }
        return (bool) $this->db->one('SELECT 1 FROM kalaqpar_accounts WHERE user_id=?', [$id]);
    }

    // Called under BEGIN IMMEDIATE, including revalidation of the request credential.
    private function current(): array { return (new Identity($this->db, $this->settings))->resolve(); }
    public function assertCanSwitch(int $id): void
    {
        if ($this->db->one('SELECT 1 FROM kalaqpar_engagements WHERE user_id=? AND departed=0', [$id])) {
            throw new GameError('engaged', 'برای ورود یا خروج از حساب، ابتدا در صفحه بازی صریحاً از صف یا اتاق خارج شوید. ثبت‌نام همان هویت فعلی را نگه می‌دارد.', 409);
        }
    }

    public function retireAll(int $id): void
    {
        $this->db->run('INSERT OR IGNORE INTO kalaqpar_retired_tokens(hash,retired_at) SELECT hash,? FROM kalaqpar_tokens WHERE user_id=?', [Settings::now(), $id]);
        $this->db->run('DELETE FROM kalaqpar_tokens WHERE user_id=?', [$id]);
    }
    private function retireCurrent(): void
    {
        $hash = hash('sha256', $_COOKIE['kq_identity']);
        $this->db->run('INSERT OR IGNORE INTO kalaqpar_retired_tokens VALUES (?,?)', [$hash, Settings::now()]);
        $this->db->run('DELETE FROM kalaqpar_tokens WHERE hash=?', [$hash]);
    }
    private function issue(int $id, string $raw): void
    {
        $this->db->run('INSERT INTO kalaqpar_tokens(hash,user_id,expires_at) VALUES (?,?,?)',
            [hash('sha256', $raw), $id, Settings::now() + $this->settings['identity_days'] * 86400000]);
    }
    public function clearBrowser(): void
    {
        foreach (['kq_identity', 'kq_boot', 'kq_account_csrf'] as $name) {
            Identity::cookie($name, '', time() - 3600);
            unset($_COOKIE[$name]);
        }
    }
    private function remember(string $raw): void
    {
        $this->clearBrowser();
        Identity::cookie('kq_identity', $raw, time() + $this->settings['identity_days'] * 86400);
        $_COOKIE['kq_identity'] = $raw;
    }

    public function register(mixed $email, mixed $password, mixed $confirmation): void
    {
        $email = self::email($email);
        $hash = password_hash(self::password($password, $confirmation), PASSWORD_BCRYPT, ['cost' => 12]);
        $raw = bin2hex(random_bytes(32));
        try {
            $this->db->atomic(function () use ($email, $hash, $raw) {
                $user = $this->current();
                if ($this->registered($user['id'])) { throw new GameError('registered', 'این حساب قبلاً ثبت‌نام شده است؛ برای تغییر حساب از ورود استفاده کنید.', 409); }
                $now = Settings::now();
                $this->db->run('INSERT INTO kalaqpar_email_accounts(user_id,email,email_canonical,password_hash,created_at,password_changed_at) VALUES (?,?,?,?,?,?)',
                    [$user['id'], $email, $email, $hash, $now, $now]);
                $this->retireAll($user['id']);
                $this->issue($user['id'], $raw);
            });
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 19 || !str_contains($e->getMessage(), 'kalaqpar_email_accounts.email_canonical')) { throw $e; }
            throw new GameError('registration', 'ثبت‌نام با این اطلاعات انجام نشد. اگر قبلاً حساب دارید، از ورود یا بازیابی رمز استفاده کنید.', 422);
        }
        $this->remember($raw);
    }

    public function login(mixed $email, mixed $password): void
    {
        try { $email = self::email($email); } catch (GameError) { $email = ''; }
        $credential = $this->db->one('SELECT * FROM kalaqpar_email_accounts WHERE email_canonical=?', [$email]);
        // A fixed bcrypt hash keeps unknown addresses on the same expensive verification path.
        $dummy = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $validInput = is_string($password) && strlen($password) <= 72 && !str_contains($password, "\0");
        $verified = password_verify($validInput ? $password : '', $credential['password_hash'] ?? $dummy);
        if (!$credential || !$validInput || !$verified) { throw new GameError('credentials', 'ایمیل یا رمز عبور درست نیست.', 422); }
        $newHash = password_needs_rehash($credential['password_hash'], PASSWORD_BCRYPT, ['cost' => 12]) ?
            password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]) : $credential['password_hash'];
        $raw = bin2hex(random_bytes(32));
        $this->db->atomic(function () use ($credential, $newHash, $raw) {
            $current = $this->current();
            $this->assertCanSwitch($current['id']);
            $fresh = $this->credential($credential['user_id']);
            if (!$fresh || !hash_equals($fresh['password_hash'], $credential['password_hash'])) {
                throw new GameError('credentials', 'ایمیل یا رمز عبور درست نیست.', 422);
            }
            $this->retireCurrent();
            $this->db->run('UPDATE kalaqpar_email_accounts SET password_hash=? WHERE user_id=?', [$newHash, $fresh['user_id']]);
            $this->issue($fresh['user_id'], $raw);
        });
        $this->remember($raw);
    }

    public function logout(): void
    {
        $this->db->atomic(function () {
            $user = $this->current();
            $this->assertCanSwitch($user['id']);
            $this->retireCurrent();
        });
        $this->clearBrowser();
    }
}
