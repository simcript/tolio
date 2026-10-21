<?php
namespace Src\Modules\Kalaqpar\Services;

use Src\Modules\Kalaqpar\Database\Connection;

final class Identity
{
    public function __construct(private Connection $db, private array $settings) {}

    public static function cookie(string $name, string $value, int $expires): void
    {
        setcookie($name, $value, ['expires' => $expires, 'path' => '/kalaqpar',
            'secure' => (bool) (Settings::values()['secure_cookie'] ?? false) || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true, 'samesite' => 'Lax']);
    }

    public function resolve(): array
    {
        $raw = $_COOKIE['kq_identity'] ?? '';
        if (!is_string($raw) || !preg_match('/^[a-f0-9]{64}$/D', $raw)) {
            throw new GameError('identity_required', 'برای ادامه صفحه را تازه کن.', 401);
        }
        $user = $this->db->one('SELECT u.*, t.expires_at FROM kalaqpar_tokens t JOIN kalaqpar_users u ON u.id=t.user_id WHERE t.hash=? AND t.expires_at>?', [hash('sha256', $raw), Settings::now()]);
        if (!$user) { throw new GameError('identity_required', 'هویت مهمان منقضی شده؛ صفحه را تازه کن.', 401); }
        $user['csrf'] = hash('sha256', 'kq-csrf:' . $raw);
        return $user;
    }

    // A private persistent key makes simultaneous bootstraps of the same browser
    // deterministic without storing plaintext guest tokens or touching host sessions.
    private function secret(): string
    {
        $f = @fopen(dirname($this->db->path) . '/identity.key', 'c+b');
        if (!$f || !flock($f, LOCK_EX)) { throw new \RuntimeException('Identity key unavailable'); }
        try {
            $key = stream_get_contents($f);
            if ($key === '') { $key = random_bytes(32); fwrite($f, $key); fflush($f); }
            if (strlen($key) !== 32) { throw new \RuntimeException('Invalid identity key'); }
            @chmod(dirname($this->db->path) . '/identity.key', 0600);
            return $key;
        } finally { flock($f, LOCK_UN); fclose($f); }
    }

    public function bootstrap(string $nonce): array
    {
        try { $user = $this->resolve(); }
        catch (GameError) {
            if (!preg_match('/^[a-f0-9]{64}$/D', $nonce)) { throw new GameError('csrf', 'صفحه را تازه کن.', 403); }
            $raw = hash_hmac('sha256', $nonce, $this->secret());
            $hash = hash('sha256', $raw);
            $user = $this->db->atomic(function () use ($hash) {
                $existing = $this->db->one('SELECT u.* FROM kalaqpar_tokens t JOIN kalaqpar_users u ON u.id=t.user_id WHERE t.hash=? AND t.expires_at>?', [$hash, Settings::now()]);
                if ($existing) { return $existing; }
                $now = Settings::now();
                $this->db->run('INSERT INTO kalaqpar_users(public_id,name,avatar,created_at,seen_at) VALUES (?,?,?,?,?)', [bin2hex(random_bytes(8)), 'مهمان ' . random_int(1000, 9999), random_int(0, 5), $now, $now]);
                $id = $this->db->id();
                $this->db->run('DELETE FROM kalaqpar_tokens WHERE hash=? AND expires_at<=?', [$hash, $now]);
                $this->db->run('INSERT INTO kalaqpar_tokens VALUES (?,?,?)', [$hash, $id, $now + $this->settings['identity_days'] * 86400000]);
                return $this->db->one('SELECT * FROM kalaqpar_users WHERE id=?', [$id]);
            });
            self::cookie('kq_identity', $raw, time() + $this->settings['identity_days'] * 86400);
            $_COOKIE['kq_identity'] = $raw;
            $user['csrf'] = hash('sha256', 'kq-csrf:' . $raw);
        }
        if (isset($user['expires_at']) && $user['expires_at'] - Settings::now() < 90 * 86400000) {
            $this->db->atomic(fn () => $this->db->run('UPDATE kalaqpar_tokens SET expires_at=? WHERE hash=?', [Settings::now() + $this->settings['identity_days'] * 86400000, hash('sha256', $_COOKIE['kq_identity'])]));
            self::cookie('kq_identity', $_COOKIE['kq_identity'], time() + $this->settings['identity_days'] * 86400);
        }
        return $user;
    }

    public function touch(int $id): void
    {
        $user = $this->db->one('SELECT seen_at FROM kalaqpar_users WHERE id=?', [$id]);
        $now = Settings::now();
        if ($user && $user['seen_at'] < $now - 3000) {
            $this->db->atomic(fn () => $this->db->run('UPDATE kalaqpar_users SET seen_at=? WHERE id=? AND seen_at<?', [$now, $id, $now - 3000]));
        }
    }

    public function limit(string $subject, string $scope, int $count, int $seconds = 60): void
    {
        $hits = $this->db->atomic(function () use ($subject, $scope, $seconds) {
            $bucket = (int) floor(time() / $seconds);
            $this->db->run('INSERT INTO kalaqpar_limits VALUES (?,?,?,1) ON CONFLICT(subject,scope,bucket) DO UPDATE SET hits=hits+1', [$subject, $scope, $bucket]);
            return $this->db->one('SELECT hits FROM kalaqpar_limits WHERE subject=? AND scope=? AND bucket=?', [$subject, $scope, $bucket])['hits'];
        });
        if ($hits > $count) { throw new GameError('rate_limited', 'کمی صبر کن و دوباره تلاش کن.', 429); }
    }
}
