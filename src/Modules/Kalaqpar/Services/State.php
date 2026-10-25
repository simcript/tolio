<?php
namespace Src\Modules\Kalaqpar\Services;

use DateTimeImmutable;
use DateTimeZone;
use Src\Modules\Kalaqpar\Database\Connection;

final class State
{
    public function __construct(private Connection $db, private array $settings) {}
    public static function week(): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'));
        $days = ((int) $now->format('w') + 1) % 7;
        $start = $now->setTime(0, 0)->modify("-{$days} days");
        return [$start->getTimestamp() * 1000, $start->modify('+7 days')->getTimestamp() * 1000];
    }
    private function user(array $u): array
    {
        return ['id' => $u['public_id'], 'name' => $u['name'], 'avatar' => $u['avatar']];
    }
    public function profile(int $id, int $page = 1): array
    {
        $u = $this->db->one('SELECT * FROM kalaqpar_users WHERE id=?', [$id]);
        [$start, $end] = self::week();
        $stats = $this->db->one('SELECT COUNT(*) settled,COALESCE(SUM(correct),0) correct FROM kalaqpar_score_events WHERE user_id=?', [$id]);
        $results = $this->db->one('SELECT COUNT(*) completed,COALESCE(SUM(won),0) wins,MAX(score) best FROM kalaqpar_results WHERE user_id=?', [$id]);
        $weekly = $this->db->one('SELECT COALESCE(SUM(delta),0) score FROM kalaqpar_score_events WHERE user_id=? AND created_at>=? AND created_at<?', [$id, $start, $end])['score'];
        $history = $this->db->all("SELECT m.id match_id,r.name,r.mode,m.phase,m.total,m.ended_at,p.score,x.rank,x.won FROM kalaqpar_match_players p JOIN kalaqpar_matches m ON m.id=p.match_id JOIN kalaqpar_rooms r ON r.id=m.room_id LEFT JOIN kalaqpar_results x ON x.match_id=m.id AND x.user_id=p.user_id WHERE p.user_id=? AND m.phase IN ('finished','technical_cancelled') ORDER BY m.id DESC LIMIT 10 OFFSET " . (($page - 1) * 10), [$id]);
        $count = $this->db->one("SELECT COUNT(*) n FROM kalaqpar_match_players p JOIN kalaqpar_matches m ON m.id=p.match_id WHERE p.user_id=? AND m.phase IN ('finished','technical_cancelled')", [$id])['n'];
        $rank = $this->leaderboard($id, 'weekly', 1, 1)['me']['rank'] ?? null;
        return $this->user($u) + ['registered' => (new Accounts($this->db, $this->settings))->registered($id), 'created_at' => $u['created_at'], 'total_score' => $u['total_score'], 'weekly_score' => $weekly, 'weekly_rank' => $rank,
            'accuracy' => $stats['settled'] ? round(100 * $stats['correct'] / $stats['settled'], 1) : null,
            'completed' => $results['completed'], 'wins' => $results['wins'], 'best' => $results['best'],
            'history' => $history, 'history_page' => $page, 'history_pages' => (int) ceil($count / 10)];
    }
    public function leaderboard(int $id, string $period, int $page, int $size = 10): array
    {
        [$start, $end] = self::week();
        $params = $period === 'weekly' ? [$start, $end] : [];
        $where = $period === 'weekly' ? 'WHERE created_at>=? AND created_at<?' : '';
        $base = "WITH totals AS (SELECT user_id,SUM(delta) score FROM kalaqpar_score_events {$where} GROUP BY user_id), ranked AS (SELECT t.*,u.public_id id,u.name,u.avatar,RANK() OVER (ORDER BY t.score DESC) rank FROM totals t JOIN kalaqpar_users u ON u.id=t.user_id) ";
        $rows = $this->db->all($base . 'SELECT id,name,avatar,score,rank FROM ranked ORDER BY score DESC,id LIMIT ' . $size . ' OFFSET ' . (($page - 1) * $size), $params);
        $me = $this->db->one($base . 'SELECT id,name,avatar,score,rank FROM ranked WHERE user_id=?', [...$params, $id]);
        $count = $this->db->one($base . 'SELECT COUNT(*) n FROM ranked', $params)['n'];
        return ['period' => $period, 'page' => $page, 'pages' => (int) ceil($count / $size), 'rows' => $rows, 'me' => $me, 'week_start' => $start, 'week_end' => $end];
    }
    public function preview(mixed $token): array
    {
        return (new Invitation($this->db, $this->settings))->preview($token);
    }
    public function forUser(int $id, ?int $requestedMatch = null): array
    {
        return $this->db->snapshot(function () use ($id, $requestedMatch) {
            $now = Settings::now();
            $worker = (new Engine($this->db, $this->settings))->healthy();
            $e = $this->db->one('SELECT * FROM kalaqpar_engagements WHERE user_id=?', [$id]);
            $state = ['server_now' => $now, 'worker_available' => $worker, 'progression_mode' => $this->settings['progression'] ?? 'worker', 'phase' => 'idle', 'scope' => 'me', 'state_version' => 0,
                'room_id' => null, 'match_id' => null, 'role' => null, 'engagement' => $e ? $e['kind'] : null];
            if ($requestedMatch) {
                $m = $this->db->one('SELECT m.* FROM kalaqpar_matches m JOIN kalaqpar_match_players p ON p.match_id=m.id AND p.user_id=? WHERE m.id=?', [$id, $requestedMatch]);
                if (!$m) { throw new GameError('forbidden', 'این مسابقه متعلق به تو نیست.', 403); }
                if (!in_array($m['phase'], ['finished','technical_cancelled'], true)) {
                    if (!$e || $e['match_id'] !== $requestedMatch) { throw new GameError('forbidden', 'این مسابقه در دسترس نیست.', 403); }
                }
                $room = $this->db->one('SELECT * FROM kalaqpar_rooms WHERE id=?', [$m['room_id']]);
                return $this->match($state, $m, $room, $id, true, $e);
            }
            if (!$e) {
                $last = $this->db->one("SELECT m.id FROM kalaqpar_match_players p JOIN kalaqpar_matches m ON m.id=p.match_id WHERE p.user_id=? AND m.phase IN ('finished','technical_cancelled') ORDER BY m.id DESC LIMIT 1", [$id]);
                $state['last_match_id'] = $last['id'] ?? null;
                return $state;
            }
            if ($e['kind'] === 'queue') {
                $q = $this->db->one("SELECT COUNT(*) n FROM kalaqpar_engagements WHERE kind='queue'")['n'];
                $w = $this->db->one('SELECT formation_at FROM kalaqpar_worker_status WHERE id=1');
                return array_replace($state, ['phase' => 'queue', 'scope' => 'queue', 'joined_at' => $e['joined_at'], 'count' => $q,
                    'capacity' => $this->settings['capacity'], 'next_at' => $w['formation_at'],
                    'position' => $this->db->one("SELECT COUNT(*) n FROM kalaqpar_engagements WHERE kind='queue' AND (joined_at<? OR (joined_at=? AND user_id<=?))", [$e['joined_at'], $e['joined_at'], $id])['n']]);
            }
            $room = $this->db->one('SELECT * FROM kalaqpar_rooms WHERE id=?', [$e['room_id']]);
            $state = array_replace($state, ['room_id' => $room['id'], 'scope' => 'room:' . $room['id'], 'state_version' => $room['version'],
                'room_name' => $room['name'], 'mode' => $room['mode'], 'capacity' => $room['capacity'], 'phase' => $room['phase'],
                'role' => $room['host_id'] === $id ? 'host' : 'guest', 'host_id' => null, 'ready' => (bool) $e['ready'],
                'departed' => (bool) $e['departed']]);
            $invitations = new Invitation($this->db, $this->settings);
            if ($invitations->valid($room)) { $state += $invitations->details($room); }
            $members = $this->db->all('SELECT e.*,u.public_id,u.name,u.avatar,u.seen_at FROM kalaqpar_engagements e JOIN kalaqpar_users u ON u.id=e.user_id WHERE e.room_id=? ORDER BY e.priority,e.joined_at,e.user_id', [$room['id']]);
            $eligible = array_slice(array_filter($members, fn ($u) => $u['ready'] && !$u['departed'] && $u['seen_at'] > $now - $this->settings['offline_ms']), 0, $room['capacity']);
            $admitted = array_column($eligible, 'user_id');
            $state['members'] = array_map(function ($u) use ($room, $now, $id, $admitted) {
                return $this->user($u) + ['me' => $u['user_id'] === $id, 'host' => $u['user_id'] === $room['host_id'],
                    'present' => !$u['departed'] && $u['seen_at'] > $now - $this->settings['offline_ms'], 'ready' => (bool) $u['ready'], 'kind' => $u['kind'], 'admitted' => in_array($u['user_id'], $admitted, true)];
            }, $members);
            $state['host_id'] = current(array_filter($state['members'], fn ($u) => $u['host']))['id'] ?? null;
            $state['waiting_count'] = count(array_filter($members, fn ($u) => $u['kind'] === 'waiting' && $u['ready']));
            $state['lobby_count'] = count($admitted);
            $state['spectator_count'] = count(array_filter($members, fn ($u) => $u['spectating']));
            $state['participant_count'] = count(array_filter($members, fn ($u) => $u['kind'] === 'match'));
            if ($room['current_match_id']) {
                $m = $this->db->one('SELECT * FROM kalaqpar_matches WHERE id=?', [$room['current_match_id']]);
                $participant = (bool) $this->db->one('SELECT 1 FROM kalaqpar_match_players WHERE match_id=? AND user_id=?', [$m['id'], $id]);
                if ($e['kind'] === 'match' || ($room['phase'] === 'playing' && $e['spectating']) || (!$e['ready'] && $participant)) {
                    return $this->match($state, $m, $room, $id, $participant, $e);
                }
                if ($room['phase'] === 'playing') {
                    return array_replace($state, ['phase' => 'waiting', 'match_id' => $m['id'], 'number' => $m['number'], 'total' => $m['total'], 'can_spectate' => true]);
                }
            }
            $state['phase'] = $room['phase'] === 'closed' ? 'closed' : 'lobby';
            $state['can_start'] = $state['role'] === 'host' && $worker && count(array_filter($state['members'], fn ($u) => $u['present'] && $u['ready'])) >= 2;
            return $state;
        });
    }
    private function match(array $state, array $m, array $room, int $id, bool $participant, ?array $e): array
    {
        $state = array_replace($state, ['room_id' => $room['id'], 'match_id' => $m['id'], 'room_name' => $room['name'], 'mode' => $room['mode'],
            'scope' => 'room:' . $room['id'], 'state_version' => $room['version'], 'phase' => $m['phase'], 'number' => $m['number'], 'total' => $m['total'],
            'starts_at' => $m['starts_at'], 'next_at' => $m['next_at'], 'role' => $participant ? 'participant' : 'spectator',
            'cancel_reason' => $m['cancel_reason'], 'round_id' => null, 'deadline_at' => null, 'item' => null, 'answer' => null, 'feedback' => null]);
        $players = $this->db->all('SELECT u.public_id id,u.name,u.avatar,u.seen_at,p.score,p.user_id,RANK() OVER (ORDER BY p.score DESC) rank FROM kalaqpar_match_players p JOIN kalaqpar_users u ON u.id=p.user_id WHERE p.match_id=? ORDER BY score DESC,u.public_id', [$m['id']]);
        $state['players'] = array_map(fn ($p) => ['id' => $p['id'], 'name' => $p['name'], 'avatar' => $p['avatar'], 'score' => $p['score'], 'rank' => $p['rank'],
            'me' => $p['user_id'] === $id, 'present' => $p['seen_at'] > Settings::now() - $this->settings['offline_ms']], $players);
        $state['me'] = current(array_filter($state['players'], fn ($p) => $p['me'])) ?: null;
        $state['participant_count'] = count($players);
        if ($m['number'] > 0 && in_array($m['phase'], ['round_active','round_feedback'], true)) {
            $r = $this->db->one('SELECT r.*,i.name,i.asset,r.category can_fly FROM kalaqpar_rounds r JOIN kalaqpar_items i ON i.id=r.item_id WHERE r.match_id=? AND r.number=?', [$m['id'], $m['number']]);
            $state['round_id'] = $r['id'];
            $state['starts_at'] = $r['starts_at'];
            $state['deadline_at'] = $r['deadline_at'];
            if (Settings::now() >= $r['starts_at']) {
                $state['item'] = ['name' => $r['name'], 'image' => PublicUrl::local(url('assets/items/' . $r['asset'] . '.svg', 'kalaqpar'), $this->settings)];
            }
            $answer = $participant ? $this->db->one('SELECT action,request_id,accepted_at FROM kalaqpar_responses WHERE round_id=? AND user_id=?', [$r['id'], $id]) : null;
            $state['answer'] = $answer;
            if ($m['phase'] === 'round_feedback' && $r['settled_at'] !== null) {
                $state['correct_action'] = $r['can_fly'] ? 'fly' : 'skip';
                $state['feedback'] = $participant ? $this->db->one('SELECT delta,correct,reason FROM kalaqpar_score_events WHERE round_id=? AND user_id=?', [$r['id'], $id]) : null;
                $speed = $this->db->one("SELECT u.public_id id,u.name FROM kalaqpar_score_events e JOIN kalaqpar_users u ON u.id=e.user_id WHERE round_id=? AND reason='first_skip'", [$r['id']]);
                $state['speed_winner'] = $speed;
            }
        }
        if (in_array($m['phase'], ['finished','technical_cancelled'], true)) {
            $state['winners'] = $m['phase'] === 'finished' ? array_values(array_filter($state['players'], fn ($p) => $p['rank'] === 1)) : [];
            $state['can_rematch'] = $room['mode'] === 'private' && $room['phase'] === 'lobby' && $e && $e['room_id'] === $room['id'];
        }
        return $state;
    }
}
