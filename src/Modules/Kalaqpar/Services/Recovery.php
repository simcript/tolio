<?php
namespace Src\Modules\Kalaqpar\Services;

use Src\Modules\Kalaqpar\Database\Connection;

final class Recovery
{
    public const RESPONSE = 'اگر حسابی با این ایمیل وجود داشته باشد، راهنمای بازیابی رمز عبور برای آن ارسال می‌شود.';
    public function __construct(private Connection $db, private array $settings) {}
    public function available(): bool
    {
        return (new AccountMailer($this->settings))->configured() &&
            ($this->db->one('SELECT heartbeat FROM kalaqpar_mail_status WHERE id=1')['heartbeat'] ?? 0) > Settings::now() - 300000;
    }
    public function request(mixed $input): void
    {
        // Every address follows the same limiter + queue path; there is no account lookup here.
        try { $email = Accounts::email($input); } catch (GameError) { $email = ''; }
        $key = hash('sha256', $email);
        $identity = new Identity($this->db, $this->settings);
        try {
            $identity->limit('ip:' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local'), 'recovery', 10, 900);
            $identity->limit('email:' . $key, 'recovery-short', 1, 300);
            $identity->limit('email:' . $key, 'recovery-hour', 6, 3600);
            $identity->limit('all', 'recovery-total', 100, 3600);
        } catch (GameError $e) { if ($e->key === 'rate_limited') { return; } throw $e; }
        $this->db->atomic(fn () => $this->db->run('INSERT OR IGNORE INTO kalaqpar_recovery_queue(email_canonical,requested_at,next_at) VALUES (?,?,?)',
            [$email, Settings::now(), Settings::now()]));
    }
    public function token(mixed $raw): ?array
    {
        if (!is_string($raw) || !preg_match('/^[a-f0-9]{64}$/D', $raw)) { return null; }
        return $this->db->one('SELECT r.* FROM kalaqpar_password_resets r JOIN kalaqpar_email_accounts a ON a.user_id=r.user_id WHERE r.hash=? AND r.expires_at>?',
            [hash('sha256', $raw), Settings::now()]);
    }
    public function reset(mixed $raw, mixed $password, mixed $confirmation): void
    {
        if (!$this->token($raw)) { throw new GameError('reset', 'پیوند نامعتبر، منقضی یا استفاده‌شده است. دوباره درخواست بازیابی بدهید.', 422); }
        $hash = password_hash(Accounts::password($password, $confirmation), PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->atomic(function () use ($raw, $hash) {
            $token = $this->token($raw);
            if (!$token) { throw new GameError('reset', 'پیوند نامعتبر، منقضی یا استفاده‌شده است. دوباره درخواست بازیابی بدهید.', 422); }
            $now = Settings::now();
            $this->db->run('UPDATE kalaqpar_email_accounts SET password_hash=?,password_changed_at=? WHERE user_id=?', [$hash, $now, $token['user_id']]);
            $this->db->run('DELETE FROM kalaqpar_password_resets WHERE user_id=?', [$token['user_id']]);
            $account = $this->db->one('SELECT email_canonical FROM kalaqpar_email_accounts WHERE user_id=?', [$token['user_id']]);
            $this->db->run('DELETE FROM kalaqpar_recovery_queue WHERE email_canonical=?', [$account['email_canonical']]);
            (new Accounts($this->db, $this->settings))->retireAll($token['user_id']);
        });
    }

    // Separate, bounded CLI dispatch avoids account-dependent SMTP latency on the public endpoint.
    public function dispatch(): int
    {
        if ($this->settings['maintenance'] ?? false) { throw new \RuntimeException('Recovery dispatch paused for maintenance.'); }
        $mailer = new AccountMailer($this->settings);
        if (!$mailer->configured()) { throw new \RuntimeException('Configure recovery SMTP and KQ_RECOVERY_ENABLED before dispatch.'); }
        $lock = fopen(dirname($this->db->path) . '/mail.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new \RuntimeException('Recovery dispatcher already running.'); }
        $sent = 0;
        try {
            $this->db->atomic(function () {
                $this->db->run('UPDATE kalaqpar_mail_status SET heartbeat=? WHERE id=1', [Settings::now()]);
                $this->db->run('DELETE FROM kalaqpar_password_resets WHERE expires_at<=?', [Settings::now()]);
                $this->db->run('DELETE FROM kalaqpar_recovery_queue WHERE requested_at<?', [Settings::now() - 3600000]);
            });
            foreach ($this->db->all('SELECT * FROM kalaqpar_recovery_queue WHERE next_at<=? ORDER BY requested_at LIMIT 5', [Settings::now()]) as $job) {
                // Token generation occurs here only; the plaintext never enters persistent storage.
                $raw = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $raw);
                $account = $this->db->atomic(function () use ($job, $tokenHash) {
                    $queued = $this->db->one('SELECT * FROM kalaqpar_recovery_queue WHERE email_canonical=? AND requested_at=?', [$job['email_canonical'], $job['requested_at']]);
                    if (!$queued) { return null; }
                    $account = $this->db->one('SELECT user_id,email,password_changed_at FROM kalaqpar_email_accounts WHERE email_canonical=?', [$job['email_canonical']]);
                    if (!$account || $account['password_changed_at'] > $job['requested_at']) {
                        $this->db->run('DELETE FROM kalaqpar_recovery_queue WHERE email_canonical=?', [$job['email_canonical']]);
                        return null;
                    }
                    $now = Settings::now();
                    // Additional emails do not revoke previously delivered links (prevents request-based lockout).
                    $this->db->run('INSERT INTO kalaqpar_password_resets VALUES (?,?,?,?)', [$tokenHash, $account['user_id'], $now + 900000, $now]);
                    return $account;
                });
                if (!$account) { continue; }
                try {
                    $url = PublicUrl::base($this->settings) . '/kalaqpar/reset-password?token=' . $raw;
                    $mailer->send($account['email'], $url);
                    $this->db->atomic(fn () => $this->db->run('DELETE FROM kalaqpar_recovery_queue WHERE email_canonical=?', [$job['email_canonical']]));
                    $sent++;
                } catch (\Throwable) {
                    // Never expose exception text, recipients, bodies, tokens or URLs in diagnostics.
                    error_log('kalaqpar recovery event=smtp_delivery_failed');
                    $this->db->atomic(function () use ($job, $tokenHash) {
                        $this->db->run('DELETE FROM kalaqpar_password_resets WHERE hash=?', [$tokenHash]);
                        if ($job['attempts'] >= 2) { $this->db->run('DELETE FROM kalaqpar_recovery_queue WHERE email_canonical=?', [$job['email_canonical']]); }
                        else { $this->db->run('UPDATE kalaqpar_recovery_queue SET attempts=attempts+1,next_at=? WHERE email_canonical=?', [Settings::now() + 300000, $job['email_canonical']]); }
                    });
                } finally { unset($raw, $url); }
            }
            return $sent;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
