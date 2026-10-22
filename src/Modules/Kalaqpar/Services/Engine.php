<?php
namespace Src\Modules\Kalaqpar\Services;

use Closure;
use Src\Modules\Kalaqpar\Database\Connection;

final class Engine
{
    private Closure $clock;
    private Closure $random;
    public function __construct(private Connection $db, private array $settings, ?Closure $clock = null, ?Closure $random = null)
    {
        $this->clock = $clock ?? Settings::now(...);
        $this->random = $random ?? random_int(...);
    }
    private function now(): int { return ($this->clock)(); }
    private function bump(int $room): void { $this->db->run('UPDATE kalaqpar_rooms SET version=version+1 WHERE id=?', [$room]); }
    private function event(string $event, int $match, ?int $round = null): void
    {
        if (@file_put_contents(dirname($this->db->path) . '/worker.log', json_encode(['at' => gmdate('c'), 'event' => $event, 'match_id' => $match, 'round_id' => $round]) . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Game log unavailable');
        }
    }
    public function healthy(): bool
    {
        if ($this->settings['maintenance'] ?? false) { return false; }
        if (!$this->db->one('SELECT version FROM kalaqpar_migrations WHERE version=4')) { return false; }
        if (($this->settings['progression'] ?? 'worker') === 'shared') {
            // An idle shared host has no daemon heartbeat. Report content readiness.
            $content = $this->db->one('SELECT COUNT(*) n, SUM(can_fly) flying FROM kalaqpar_items WHERE enabled=1');
            return $content['n'] >= 100 && $content['flying'] >= 30;
        }
        $w = $this->db->one('SELECT * FROM kalaqpar_worker_status WHERE id=1');
        return $w && $w['running'] === 1 && $w['heartbeat'] > $this->now() - $this->settings['health_ms'];
    }
    private function requireWorker(): void
    {
        if (!$this->healthy()) { throw new GameError('worker_unavailable', 'سرویس بازی در دسترس نیست؛ پاسخ‌های ثبت‌شده محفوظ هستند.', 503); }
    }
    private function engagement(int $id): ?array { return $this->db->one('SELECT * FROM kalaqpar_engagements WHERE user_id=?', [$id]); }
    private function free(int $id): void
    {
        if ($this->engagement($id)) { throw new GameError('engaged', 'ابتدا به بازی فعلی برگرد یا از صف و اتاق قبلی خارج شو.'); }
    }
    private function room(int $id): array
    {
        $room = $this->db->one('SELECT * FROM kalaqpar_rooms WHERE id=?', [$id]);
        if (!$room || $room['phase'] === 'closed') { throw new GameError('room_closed', 'این اتاق بسته شده است.', 410); }
        return $room;
    }
    private function member(int $id, int $room): array
    {
        $e = $this->engagement($id);
        if (!$e || $e['room_id'] !== $room) { throw new GameError('forbidden', 'دسترسی به این اتاق نداری.', 403); }
        return $e;
    }
    private function present(array $e, int $now): bool
    {
        return !$e['departed'] && $e['seen_at'] > $now - $this->settings['offline_ms'];
    }
    private function members(int $room): array
    {
        return $this->db->all('SELECT e.*,u.seen_at FROM kalaqpar_engagements e JOIN kalaqpar_users u ON u.id=e.user_id WHERE e.room_id=? ORDER BY e.priority,e.joined_at,e.user_id', [$room]);
    }
    public function queue(int $id): void
    {
        $this->db->atomic(function () use ($id) {
            $e = $this->engagement($id);
            if ($e && $e['kind'] === 'queue') { return; }
            $this->free($id);
            $this->requireWorker();
            $this->db->run("INSERT INTO kalaqpar_engagements(user_id,kind,joined_at) VALUES (?,'queue',?)", [$id, $this->now()]);
        });
    }
    public function create(int $id, array $input): array
    {
        $name = Settings::name($input['name'] ?? '', 40);
        $capacity = filter_var($input['capacity'] ?? null, FILTER_VALIDATE_INT);
        if (!$capacity || $capacity < 2 || $capacity > 30) { throw new GameError('capacity', 'ظرفیت باید از ۲ تا ۳۰ نفر باشد.', 422); }
        return $this->db->atomic(function () use ($id, $name, $capacity) {
            $this->free($id);
            PublicUrl::base($this->settings);
            $now = $this->now();
            Invitation::allocate($this->db, fn ($code) => $this->db->run("INSERT INTO kalaqpar_rooms(mode,name,capacity,invitation,host_id,created_at) VALUES ('private',?,?,?,?,?)", [$name, $capacity, $code, $id, $now]));
            $room = $this->db->id();
            $this->db->run("INSERT INTO kalaqpar_engagements(user_id,kind,room_id,joined_at,priority) VALUES (?,'lobby',?,?,0)", [$id, $room, $now]);
            return ['room_id' => $room] + (new Invitation($this->db, $this->settings))->details($this->room($room));
        });
    }
    public function join(int $id, string $invitation): void
    {
        $this->db->atomic(function () use ($id, $invitation) {
            $invitations = new Invitation($this->db, $this->settings);
            $room = $invitations->resolve($invitation, $this->now());
            $e = $this->engagement($id);
            if ($e && $e['room_id'] === $room['id']) { return; }
            $this->free($id);
            $active = $room['phase'] === 'playing';
            if (!$invitations->available($room)) { throw new GameError($active ? 'waiting_full' : 'room_full', $active ? 'فهرست انتظار دست بعدی پر است.' : 'ظرفیت اتاق پر شده است.'); }
            $this->db->run('INSERT INTO kalaqpar_engagements(user_id,kind,room_id,joined_at) VALUES (?,?,?,?)', [$id, $active ? 'waiting' : 'lobby', $room['id'], $this->now()]);
            $this->bump($room['id']);
        });
    }
    public function start(int $id, int $roomId): void
    {
        $this->db->atomic(function () use ($id, $roomId) {
            $this->member($id, $roomId);
            $room = $this->room($roomId);
            if ($room['host_id'] !== $id) { throw new GameError('host_only', 'فقط میزبان می‌تواند بازی را شروع کند.', 403); }
            if ($room['phase'] === 'playing') {
                $m = $this->db->one('SELECT phase FROM kalaqpar_matches WHERE id=?', [$room['current_match_id']]);
                if ($m && $m['phase'] === 'countdown') { return; }
                throw new GameError('already_started', 'مسابقه در حال برگزاری است.');
            }
            $this->requireWorker();
            $members = array_values(array_filter($this->members($roomId), fn ($e) => $e['ready'] && $this->present($e, $this->now())));
            $members = array_slice($members, 0, $room['capacity']);
            if (count($members) < 2) { throw new GameError('need_players', 'حداقل دو بازیکن آنلاین و آماده لازم است.'); }
            $this->newMatch($room, $members, $this->now());
        });
    }
    private function newMatch(array $room, array $members, int $now): void
    {
        $starts = $now + $this->settings['countdown_ms'];
        $snapshot = $this->settingsSnapshot();
        $snapshot['flying_target'] = intdiv($this->settings['rounds'], 2) + ($this->settings['rounds'] % 2 ? ($this->random)(0, 1) : 0);
        $this->db->run("INSERT INTO kalaqpar_matches(room_id,phase,total,settings,starts_at,next_at,created_at) VALUES (?,'countdown',?,?,?,?,?)", [$room['id'], $this->settings['rounds'], json_encode($snapshot), $starts, $starts, $now]);
        $match = $this->db->id();
        foreach ($members as $e) {
            $this->db->run('INSERT INTO kalaqpar_match_players(match_id,user_id) VALUES (?,?)', [$match, $e['user_id']]);
            $this->db->run("UPDATE kalaqpar_engagements SET kind='match',room_id=?,match_id=?,spectating=0,departed=0 WHERE user_id=?", [$room['id'], $match, $e['user_id']]);
        }
        $this->db->run("UPDATE kalaqpar_rooms SET phase='playing',current_match_id=?,empty_since=NULL,version=version+1 WHERE id=?", [$match, $room['id']]);
        $this->event('match_countdown', $match);
    }
    private function settingsSnapshot(): array
    {
        return array_intersect_key($this->settings, array_flip(['window_ms','window_step_ms','window_floor_ms','feedback_ms','countdown_ms']));
    }
    public function leave(int $id): void
    {
        $this->db->atomic(function () use ($id) {
            $e = $this->engagement($id);
            if (!$e) { return; }
            $match = $e['match_id'] ? $this->db->one('SELECT * FROM kalaqpar_matches WHERE id=?', [$e['match_id']]) : null;
            if ($match && in_array($match['phase'], ['round_active','round_feedback'], true)) {
                $this->db->run('UPDATE kalaqpar_engagements SET departed=1 WHERE user_id=?', [$id]);
            } else {
                if ($match && $match['phase'] === 'countdown') { $this->db->run('DELETE FROM kalaqpar_match_players WHERE match_id=? AND user_id=?', [$match['id'], $id]); }
                $this->db->run('DELETE FROM kalaqpar_engagements WHERE user_id=?', [$id]);
            }
            if ($e['room_id']) {
                $this->db->run('UPDATE kalaqpar_rooms SET host_id=NULL WHERE id=? AND host_id=?', [$e['room_id'], $id]);
                $this->transfer($this->room($e['room_id']), $this->now());
                $this->bump($e['room_id']);
            }
        });
    }
    public function returnToMatch(int $id): void
    {
        $this->db->atomic(function () use ($id) {
            $e = $this->engagement($id);
            if (!$e || $e['kind'] !== 'match') { throw new GameError('not_playing', 'مسابقه فعالی نداری.'); }
            $this->db->run('UPDATE kalaqpar_engagements SET departed=0 WHERE user_id=?', [$id]);
            $this->bump($e['room_id']);
        });
    }
    public function spectate(int $id, int $roomId): void
    {
        $this->db->atomic(function () use ($id, $roomId) {
            $e = $this->member($id, $roomId);
            if ($e['kind'] !== 'waiting') { throw new GameError('not_waiting', 'ابتدا به فهرست انتظار بپیوند.'); }
            $this->db->run('UPDATE kalaqpar_engagements SET spectating=1 WHERE user_id=?', [$id]);
            $this->bump($roomId);
        });
    }
    public function rematch(int $id, int $roomId): void
    {
        $this->db->atomic(function () use ($id, $roomId) {
            $this->member($id, $roomId);
            $r = $this->room($roomId);
            if ($r['mode'] !== 'private' || $r['phase'] === 'playing') { throw new GameError('phase', 'در این مرحله بازگشت به لابی ممکن نیست.'); }
            $this->db->run("UPDATE kalaqpar_engagements SET kind='lobby',ready=1,departed=0,spectating=0 WHERE user_id=?", [$id]);
            $this->bump($roomId);
        });
    }
    public function answer(int $id, array $input): array
    {
        $action = $input['action'] ?? null;
        $request = $input['request_id'] ?? null;
        if (!in_array($action, ['fly','skip'], true) || !is_string($request) || !preg_match('/^[a-zA-Z0-9_-]{16,80}$/D', $request)) {
            throw new GameError('invalid_answer', 'پاسخ معتبر نیست.', 422);
        }
        $roundId = filter_var($input['round_id'] ?? null, FILTER_VALIDATE_INT);
        $matchId = filter_var($input['match_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$roundId || !$matchId) { throw new GameError('invalid_round', 'شناسه دور معتبر نیست.', 422); }
        $elapsed = $input['client_elapsed_ms'] ?? null;
        $elapsed = is_numeric($elapsed) && $elapsed >= 0 && $elapsed <= 10000 ? (int) $elapsed : null;
        return $this->db->atomic(function () use ($id, $elapsed, $action, $request, $roundId, $matchId) {
            // Time is captured AFTER BEGIN IMMEDIATE has acquired the write lock.
            $now = $this->now();
            $round = $this->db->one('SELECT r.*,m.phase,m.room_id FROM kalaqpar_rounds r JOIN kalaqpar_matches m ON m.id=r.match_id JOIN kalaqpar_match_players p ON p.match_id=m.id AND p.user_id=? WHERE r.id=? AND m.id=?', [$id, $roundId, $matchId]);
            if (!$round) { throw new GameError('forbidden', 'اجازه پاسخ‌دادن در این مسابقه را نداری.', 403); }
            $previousRequest = $this->db->one('SELECT * FROM kalaqpar_responses WHERE user_id=? AND request_id=?', [$id, $request]);
            if ($previousRequest) {
                if ($previousRequest['round_id'] !== $roundId || $previousRequest['action'] !== $action || $previousRequest['elapsed_ms'] !== $elapsed) { throw new GameError('request_conflict', 'شناسه درخواست برای پاسخ دیگری استفاده شده است.'); }
                return ['accepted' => true, 'action' => $action, 'request_id' => $request, 'accepted_at' => $previousRequest['accepted_at'], 'state_version' => $this->db->one('SELECT version FROM kalaqpar_rooms WHERE id=?', [$round['room_id']])['version']];
            }
            $old = $this->db->one('SELECT action FROM kalaqpar_responses WHERE round_id=? AND user_id=?', [$roundId, $id]);
            if ($old) { throw new GameError('answer_locked', 'پاسخ این دور قبلاً ثبت شده است.'); }
            $this->requireWorker();
            if ($round['phase'] !== 'round_active' || $round['settled_at'] !== null || $now < $round['starts_at'] || $now >= $round['deadline_at']) {
                throw new GameError('answer_late', 'زمان پاسخ این دور تمام شده یا هنوز شروع نشده است.');
            }
            $this->db->run('INSERT INTO kalaqpar_responses(round_id,user_id,action,request_id,accepted_at,elapsed_ms) VALUES (?,?,?,?,?,?)', [$roundId, $id, $action, $request, $now, $elapsed]);
            $this->bump($round['room_id']);
            return ['accepted' => true, 'action' => $action, 'request_id' => $request, 'accepted_at' => $now, 'state_version' => $this->db->one('SELECT version FROM kalaqpar_rooms WHERE id=?', [$round['room_id']])['version']];
        });
    }
    private function transfer(array $room, int $now): void
    {
        if ($room['mode'] !== 'private') { return; }
        $members = $this->members($room['id']);
        foreach ($members as $m) {
            if ($m['user_id'] === $room['host_id'] && !$m['departed'] && $m['seen_at'] > $now - $this->settings['host_transfer_ms']) { return; }
        }
        // Host seniority uses arrival, independently of rematch admission priority.
        usort($members, fn ($a, $b) => [$a['joined_at'],$a['user_id']] <=> [$b['joined_at'],$b['user_id']]);
        $host = null;
        foreach ($members as $m) { if ($this->present($m, $now)) { $host = $m['user_id']; break; } }
        if ($host !== $room['host_id']) {
            $this->db->run('UPDATE kalaqpar_rooms SET host_id=?,version=version+1 WHERE id=?', [$host, $room['id']]);
        }
    }
    public function tick(): void
    {
        $this->db->atomic(function () {
            $now = $this->now();
            $old = $this->db->one('SELECT * FROM kalaqpar_worker_status WHERE id=1');
            // Crash recovery: invalidate only unsettled rounds; retain ledger/history.
            if (!$old || $old['heartbeat'] < $now - $this->settings['outage_ms']) {
                foreach ($this->db->all("SELECT * FROM kalaqpar_matches WHERE phase IN ('countdown','round_active','round_feedback')") as $m) { $this->finish($m, $now, true); }
            }
        });
        $this->db->atomic(fn () => $this->matchmaking());
        // Each match is a separate short transaction so other rooms can submit.
        foreach ($this->db->all("SELECT id FROM kalaqpar_matches WHERE phase IN ('countdown','round_active','round_feedback')") as $row) {
            $this->db->atomic(fn () => $this->advance($row['id']));
        }
        // Persist the cleanup throttle: shared-host requests create fresh Engine objects.
        $cleanup = $this->db->one('SELECT cleanup_at FROM kalaqpar_worker_status WHERE id=1')['cleanup_at'];
        if ($cleanup <= $this->now() - 1000) {
            foreach ($this->db->all("SELECT id FROM kalaqpar_rooms WHERE phase<>'closed'") as $row) {
                $this->db->atomic(function () use ($row) {
                    $r = $this->room($row['id']); $now = $this->now();
                    $this->transfer($r, $now);
                    if ($r['phase'] !== 'playing') {
                        $online = array_filter($this->members($r['id']), fn ($e) => $this->present($e, $now));
                        if ($online) { $this->db->run('UPDATE kalaqpar_rooms SET empty_since=NULL WHERE id=?', [$r['id']]); }
                        elseif (!$r['empty_since']) { $this->db->run('UPDATE kalaqpar_rooms SET empty_since=? WHERE id=?', [$now, $r['id']]); }
                        elseif ($r['empty_since'] < $now - $this->settings['room_ttl_ms']) {
                            $this->db->run("UPDATE kalaqpar_rooms SET phase='closed',host_id=NULL,version=version+1 WHERE id=?", [$r['id']]);
                            $this->db->run('DELETE FROM kalaqpar_engagements WHERE room_id=?', [$r['id']]);
                        }
                    }
                });
            }
            $this->db->atomic(function () {
                $this->db->run('DELETE FROM kalaqpar_tokens WHERE expires_at<?', [$this->now()]);
                $this->db->run('DELETE FROM kalaqpar_limits WHERE bucket<?', [(int) floor(time() / 60) - 1440]);
                $this->db->run('UPDATE kalaqpar_worker_status SET cleanup_at=? WHERE id=1', [$this->now()]);
            });
        }
        // Report successful progression, not merely a loop which keeps failing.
        $now = $this->now();
        $status = $this->db->one('SELECT heartbeat,running FROM kalaqpar_worker_status WHERE id=1');
        if (($this->settings['progression'] ?? 'worker') === 'shared' || !$status['running'] || $status['heartbeat'] < $now - 500) {
            $this->db->atomic(fn () => $this->db->run('UPDATE kalaqpar_worker_status SET heartbeat=?,running=1 WHERE id=1', [$this->now()]));
        }
    }
    private function matchmaking(): void
    {
        $now = $this->now();
        $this->db->run("DELETE FROM kalaqpar_engagements WHERE kind='queue' AND user_id IN (SELECT id FROM kalaqpar_users WHERE seen_at<=?)", [$now - $this->settings['offline_ms']]);
        $players = $this->db->all("SELECT * FROM kalaqpar_engagements WHERE kind='queue' ORDER BY joined_at,user_id LIMIT 30");
        $formation = $this->db->one('SELECT formation_at FROM kalaqpar_worker_status WHERE id=1')['formation_at'];
        if (count($players) < 2) { $this->db->run('UPDATE kalaqpar_worker_status SET formation_at=NULL WHERE id=1'); return; }
        if ($formation === null) {
            $formation = $now + $this->settings['formation_ms'];
            $this->db->run('UPDATE kalaqpar_worker_status SET formation_at=? WHERE id=1', [$formation]);
        }
        if (count($players) >= $this->settings['capacity'] || $now >= $formation) {
            $this->db->run("INSERT INTO kalaqpar_rooms(mode,name,capacity,created_at) VALUES ('public','بازی سریع',?,?)", [$this->settings['capacity'], $now]);
            $room = $this->room($this->db->id());
            $this->newMatch($room, array_slice($players, 0, $this->settings['capacity']), $now);
            $this->db->run('UPDATE kalaqpar_worker_status SET formation_at=NULL WHERE id=1');
        }
    }
    private function advance(int $id): void
    {
        $m = $this->db->one('SELECT * FROM kalaqpar_matches WHERE id=?', [$id]);
        $now = $this->now();
        if ($m['phase'] === 'countdown') {
            $present = $this->db->one('SELECT COUNT(*) n FROM kalaqpar_match_players p JOIN kalaqpar_users u ON u.id=p.user_id JOIN kalaqpar_engagements e ON e.user_id=p.user_id AND e.match_id=p.match_id WHERE p.match_id=? AND u.seen_at>? AND e.departed=0', [$id, $now - $this->settings['offline_ms']])['n'];
            if ($present < 2) { $this->cancelCountdown($m); return; }
        }
        if ($now < $m['next_at']) { return; }
        if ($m['phase'] === 'countdown' || $m['phase'] === 'round_feedback') {
            if ($m['number'] >= $m['total']) { $this->finish($m, $now); return; }
            $this->beginRound($m, $now);
        } elseif ($m['phase'] === 'round_active') {
            $this->settle($m, $now);
        }
    }
    private function cancelCountdown(array $m): void
    {
        $r = $this->room($m['room_id']);
        if ($r['mode'] === 'public') {
            $this->db->run("UPDATE kalaqpar_engagements SET kind='queue',room_id=NULL,match_id=NULL WHERE match_id=?", [$m['id']]);
            $this->db->run("UPDATE kalaqpar_rooms SET phase='closed',version=version+1 WHERE id=?", [$r['id']]);
            $this->db->run('UPDATE kalaqpar_worker_status SET formation_at=NULL WHERE id=1');
        } else {
            $this->db->run("UPDATE kalaqpar_engagements SET kind='lobby',match_id=NULL WHERE room_id=? AND kind='match'", [$r['id']]);
            $this->db->run('UPDATE kalaqpar_engagements SET spectating=0 WHERE room_id=?', [$r['id']]);
            $this->db->run("UPDATE kalaqpar_rooms SET phase='lobby',current_match_id=NULL,version=version+1 WHERE id=?", [$r['id']]);
        }
        $this->db->run("UPDATE kalaqpar_matches SET phase='countdown_cancelled',ended_at=? WHERE id=?", [$this->now(), $m['id']]);
        $this->event('countdown_cancelled', $m['id']);
    }
    private function beginRound(array $m, int $now): void
    {
        $recent = array_column($this->db->all('SELECT item_id FROM kalaqpar_rounds WHERE match_id=? ORDER BY number DESC LIMIT 5', [$m['id']]), 'item_id');
        $settings = json_decode($m['settings'], true);
        $usedFlying = (int) $this->db->one('SELECT COALESCE(SUM(category),0) n FROM kalaqpar_rounds WHERE match_id=?', [$m['id']])['n'];
        $remaining = $m['total'] - $m['number'];
        $target = $settings['flying_target'] ?? intdiv($m['total'], 2);
        $category = (int) (($this->random)(1, $remaining) <= max(0, $target - $usedFlying));
        $items = $this->db->all('SELECT id FROM kalaqpar_items WHERE enabled=1 AND can_fly=? ORDER BY id', [$category]);
        $items = array_values(array_filter($items, fn ($i) => !in_array($i['id'], $recent, true)));
        if (!$items) { throw new \RuntimeException('Seed content missing'); }
        $item = $items[($this->random)(0, count($items) - 1)]['id'];
        $number = $m['number'] + 1;
        $window = max($settings['window_floor_ms'], $settings['window_ms'] - $settings['window_step_ms'] * intdiv($number - 1, 5));
        $deadline = $now + $window;
        $category = $this->db->one('SELECT can_fly FROM kalaqpar_items WHERE id=?', [$item])['can_fly'];
        $this->db->run('INSERT INTO kalaqpar_rounds(match_id,number,item_id,starts_at,deadline_at,category) VALUES (?,?,?,?,?,?)', [$m['id'], $number, $item, $now, $deadline, $category]);
        $roundId = $this->db->id();
        $this->db->run("UPDATE kalaqpar_matches SET phase='round_active',number=?,next_at=? WHERE id=?", [$number, $deadline, $m['id']]);
        $this->bump($m['room_id']);
        $this->event('round_started', $m['id'], $roundId);
    }
    private function settle(array $m, int $now): void
    {
        $round = $this->db->one('SELECT r.*,r.category can_fly FROM kalaqpar_rounds r WHERE r.match_id=? AND r.number=?', [$m['id'], $m['number']]);
        if (!$round || $round['settled_at'] !== null || $now < $round['deadline_at']) { return; }
        $speed = $round['can_fly'] ? null : $this->db->one("SELECT user_id FROM kalaqpar_responses WHERE round_id=? AND action='skip' ORDER BY sequence LIMIT 1", [$round['id']]);
        foreach ($this->db->all('SELECT p.user_id,a.action FROM kalaqpar_match_players p LEFT JOIN kalaqpar_responses a ON a.user_id=p.user_id AND a.round_id=? WHERE p.match_id=?', [$round['id'], $m['id']]) as $p) {
            $correct = $p['action'] !== null && ($round['can_fly'] ? $p['action'] === 'fly' : $p['action'] === 'skip');
            $delta = $correct ? ($round['can_fly'] || ($speed && $speed['user_id'] === $p['user_id']) ? 1 : 0) : -1;
            $reason = $p['action'] === null ? 'timeout' : ($correct ? ($delta === 0 ? 'correct_skip_zero' : ($round['can_fly'] ? 'correct_fly' : 'first_skip')) : 'wrong');
            $inserted = $this->db->run('INSERT INTO kalaqpar_score_events(user_id,match_id,round_id,delta,correct,reason,created_at) VALUES (?,?,?,?,?,?,?) ON CONFLICT(round_id,user_id) DO NOTHING', [$p['user_id'], $m['id'], $round['id'], $delta, (int) $correct, $reason, $now])->rowCount();
            if ($inserted) {
                $this->db->run('UPDATE kalaqpar_match_players SET score=score+? WHERE match_id=? AND user_id=?', [$delta, $m['id'], $p['user_id']]);
                $this->db->run('UPDATE kalaqpar_users SET total_score=total_score+? WHERE id=?', [$delta, $p['user_id']]);
            }
        }
        $this->db->run('UPDATE kalaqpar_rounds SET settled_at=? WHERE id=?', [$now, $round['id']]);
        $settings = json_decode($m['settings'], true);
        $this->db->run("UPDATE kalaqpar_matches SET phase='round_feedback',next_at=? WHERE id=?", [$now + $settings['feedback_ms'], $m['id']]);
        $this->bump($m['room_id']);
        $this->event('round_settled', $m['id'], $round['id']);
    }
    private function finish(array $m, int $now, bool $cancelled = false): void
    {
        if (!in_array($m['phase'], ['countdown','round_active','round_feedback'], true)) { return; }
        $phase = $cancelled ? 'technical_cancelled' : 'finished';
        $this->event($phase, $m['id']);
        $this->db->run('UPDATE kalaqpar_matches SET phase=?,ended_at=?,cancel_reason=? WHERE id=?', [$phase, $now, $cancelled ? 'worker_outage' : null, $m['id']]);
        if (!$cancelled) {
            $players = $this->db->all('SELECT p.*,RANK() OVER (ORDER BY p.score DESC) rank FROM kalaqpar_match_players p WHERE match_id=?', [$m['id']]);
            foreach ($players as $p) { $this->db->run('INSERT INTO kalaqpar_results VALUES (?,?,?,?,?) ON CONFLICT(match_id,user_id) DO NOTHING', [$m['id'], $p['user_id'], $p['score'], $p['rank'], (int) ($p['rank'] === 1)]); }
        }
        $r = $this->room($m['room_id']);
        if ($r['mode'] === 'private') {
            $this->db->run("UPDATE kalaqpar_engagements SET kind='waiting',match_id=NULL,ready=0,priority=0,spectating=0 WHERE match_id=?", [$m['id']]);
            $this->db->run('UPDATE kalaqpar_engagements SET spectating=0 WHERE room_id=?', [$r['id']]);
            $this->db->run("UPDATE kalaqpar_rooms SET phase='lobby',version=version+1 WHERE id=?", [$r['id']]);
        } else {
            // Keep results visible through an explicit read of the last match.
            $this->db->run('DELETE FROM kalaqpar_engagements WHERE match_id=?', [$m['id']]);
            $this->db->run("UPDATE kalaqpar_rooms SET phase='closed',version=version+1 WHERE id=?", [$r['id']]);
        }
    }
    public function rebuild(): void
    {
        $this->db->atomic(function () {
            $this->db->run('UPDATE kalaqpar_users SET total_score=COALESCE((SELECT SUM(delta) FROM kalaqpar_score_events e WHERE e.user_id=kalaqpar_users.id),0)');
            $this->db->run('UPDATE kalaqpar_match_players SET score=COALESCE((SELECT SUM(delta) FROM kalaqpar_score_events e WHERE e.match_id=kalaqpar_match_players.match_id AND e.user_id=kalaqpar_match_players.user_id),0)');
            $this->db->run('UPDATE kalaqpar_results SET score=(SELECT score FROM kalaqpar_match_players p WHERE p.match_id=kalaqpar_results.match_id AND p.user_id=kalaqpar_results.user_id)');
            $this->db->run('UPDATE kalaqpar_results SET rank=1+(SELECT COUNT(*) FROM kalaqpar_match_players p WHERE p.match_id=kalaqpar_results.match_id AND p.score>kalaqpar_results.score)');
            $this->db->run('UPDATE kalaqpar_results SET won=(rank=1)');
            $this->db->run('UPDATE kalaqpar_rooms SET version=version+1');
        });
    }
}
