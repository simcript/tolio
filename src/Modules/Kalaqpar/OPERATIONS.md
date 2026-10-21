# Kalaqpar installation and operations

Implemented in the host Pano 1.7.3 application, under namespace `Src\Modules\Kalaqpar`. Read `README.md` for authoritative rules and the owner override. This is an HTTP polling game, not the legacy Guessing word game. The application entry point remains `public/index.php` and the CLI entry point remains `pano`.

**No test code was authored or modified. Do not add tests, fixtures, test helpers/scripts, testing dependencies or testing CI for this task. Existing tests are untouched.** Syntax checking and direct manual HTTP/CLI inspection are the permitted verification methods. Deployment templates are complete examples, not a claim that systemd, Nginx or PHP-FPM were installed or deployed in this workspace.

## Prerequisites and initial setup

- PHP 8.2+ with PDO, `pdo_sqlite`, JSON, PCRE Unicode and a writable local filesystem. SQLite 3.25+ is required for window functions; 3.27+ for `VACUUM INTO` backups. Use a maintained SQLite build. `pcntl` is recommended on Linux for SIGINT/SIGTERM handling; `kalaqpar:stop` also works without it.
- Production Composer installation without development dependencies supports the PHP 8.2 framework baseline. Existing locked PHPUnit development packages require PHP 8.3+ if installed; this task does not use or modify them.
- A persistent CLI process, HTTPS, and one server using a single local-disk SQLite file. Shared hosting without a persistent process, NFS/shared network storage, multiple worker instances and multiple application servers are unsupported.
- The web process and worker must use the same `.env`, database path, identity key directory and OS user/group permissions. On production use a Linux local disk, not a Windows-mounted/network directory.

From the repository root:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
php -r 'echo implode(",", PDO::getAvailableDrivers()), PHP_EOL;'
php pano kalaqpar kalaqpar:migrate
php pano kalaqpar kalaqpar:seed
```

Set these values in the existing root `.env` (do not replace host configuration or reuse the older `KALAQPAR_*` names):

```dotenv
APP_ENV=production
APP_DEBUG=false
KQ_DATABASE=/var/lib/kalaqpar/game.sqlite
KQ_SECURE_COOKIE=true
```

`KQ_DATABASE` defaults to `src/Modules/Kalaqpar/Files/runtime/game.sqlite`, outside `public/` and ignored by Git. Keep the database path absolute in production. The first connection creates its directory with mode 0700 and database with 0600; the worker/service should run with umask 0077. Both web and worker need directory write permission for WAL/SHM, the lock, logs, stop request, identity key and snapshots. Do not make storage world-writable. Set up production storage, for example:

```sh
sudo install -d -o www-data -g www-data -m 0700 /var/lib/kalaqpar
sudo -u www-data php /srv/tools/pano kalaqpar kalaqpar:migrate
sudo -u www-data php /srv/tools/pano kalaqpar kalaqpar:seed
```

Migrations are versioned and transactional: version 1 creates prefixed tables, constraints, indexes and immutable-ledger triggers; version 2 snapshots the private answer category into each round. Repeating either command does not reset identities, scores or history. The seed contains 111 reviewed Persian names with 39 flying and 72 non-flying items. It upserts production content, not players, matches or scores. Stop the worker during schema/content maintenance.

## Development web and persistent worker

Use two terminals from the repository root:

```sh
php -S 127.0.0.1:8088 -t public
php pano kalaqpar kalaqpar:worker
```

Open `http://127.0.0.1:8088/kalaqpar/`. PHP's development server resolves the Pano front controller for these module paths; bundled essential assets use the module asset handler. Production must use PHP-FPM/Apache, not the PHP development server. Do not enable the secure-cookie override on plain-HTTP localhost; HTTPS always causes the identity cookies to use Secure. Configure `HTTPS` correctly when using a trusted reverse proxy; the application does not trust arbitrary forwarded headers.

The worker runs until SIGINT/SIGTERM or the portable stop request:

```sh
php pano kalaqpar kalaqpar:stop
```

Only one worker or maintenance process obtains `worker.lock` via nonblocking `flock`. A second worker fails rather than racing. The stop command writes a private stop request; the worker consumes it after its current short tick and marks itself stopped. Wait for the process to exit before migration/rebuild. On Windows, use this stop command instead of relying on POSIX signals. Workers already loaded before a code update must be restarted.

The worker alone creates public matches, starts and settles rounds, handles timeouts/results, host transfer, stale queues and room expiry. Polling has no engine tick or lifecycle side effects. A POST heartbeat updates presence at most once every three seconds; the page normally sends one every 4.5 seconds. Read endpoints never enroll guests in a queue or room.

## Configuration and protocol

Host config `config/kalaqpar.php` loads module `Files/config.php`. Edit these module settings for a deployment and restart the worker. Match timing, round count and the random category target are snapshotted at creation; changing config does not change an active match.

| Setting | Default |
|---|---:|
| Rounds / public capacity | 15 / 30 |
| Private capacity | host selects 2–30, UI default 30 |
| Initial countdown | 3000ms |
| Answer window | 2500ms minus 200ms every 5 rounds, floor 1500ms |
| Feedback interval | 2000ms, including the next-round wait |
| Public formation after second arrival | 12000ms, or earlier at capacity |
| Worker tick | 100ms |
| Successful worker heartbeat write | at most every 500ms |
| Worker unavailable / long outage | 2000ms heartbeat age / 10000ms |
| Offline / host transfer | 15000ms / 30000ms; immediate on explicit departure |
| Empty-room expiry | 1800000ms |
| Guest identity lifetime | 180 days; renewal when less than 90 days remain |
| Active / lobby / idle UI polling | 300 / 1000 / 5000ms |
| Hidden-page polling | 4000ms |
| Error backoff | exponential, capped around 15s, with jitter |
| SQLite busy timeout / lock acquisition attempts | 1000ms / 3 bounded attempts |

All API times use UTC epoch **milliseconds**. Weeks start Saturday 00:00 `Asia/Tehran`, converted to UTC, with half-open ledger intervals. Shared competition ranks use score only; public player IDs order ties without breaking joint wins. A match randomly contains seven or eight flying items, sampled without predictable alternation. Items from the previous five rounds are excluded. Only the current item is disclosed; future selections and private categories stay server-side. Hash-named SVGs have no answer metadata.

`state_version` increases within the room scope on lifecycle/membership changes and answer acceptance. An acknowledgement carries its committed version so an older in-flight poll cannot unlock the buttons. The frontend discards older versions within a scope and discards replies from a previous navigation/requested match. Presence may refresh at the same version. It keeps one state poll in flight, takes RTT/offset samples for display, locks uncertain selections, stores only pending operation IDs/actions (never auth tokens) in localStorage, and retries the exact request ID. Browser timing is diagnostic only; the acceptance clock is read after obtaining SQLite's write lock. An answer at the deadline is late.

Admission is explicit. Visiting an invitation performs a limited preview; Join is a CSRF-protected POST. Active rosters are frozen. Late invitees enter a waiting list bounded by room capacity and may explicitly authorize spectating. Spectators are a subset of the waiting list, not extra answer participants. After private results, previous participants retain room membership in an unready result state. “Play again” opts them in; prior participants have admission priority and then incoming invitees have FIFO priority. The host starts manually again, freezing at most capacity. A public replay leaves results and enters a new public FIFO queue.

An explicit departure during active gameplay does not delete participation or permit another engagement. The user can return; unanswered rounds still settle once at -1. Offline users do not disappear from an active roster. A countdown with fewer than two present participants is canceled without results and returns eligible players to waiting. A long worker outage technically cancels active matches, keeps already-settled ledger scores, invalidates the open round, and records no competitive wins/completed result. A short restart resumes persisted phases without duplicate settlements. Stale or explicitly stopped workers reject new answers/starts but preserve accepted answers.

Limits are independent per authenticated identity: room creation 6/minute, invitation/queue/leave/rematch/spectate/resume operations 30/minute, start 20/minute, answers 180/minute, name/avatar updates 10/minute. Bootstrap is 300/minute per hashed IP to allow users behind NAT; anonymous invitation previews are 60/minute per hashed IP. Limits and input validation are in `Identity`/`ApiHandler`; pagination is 10/page with bounded page input; API bodies are limited to 4096 bytes, with a matching web-server limit. No unrestricted CORS is enabled.

## HTTP inventory

All routes below are under `/kalaqpar`. Pano registers them in `KalaqparModule::setup()`.

| Method | Path | Purpose |
|---|---|---|
| GET | `/`, `/r/[token]` | Persian interface and explicit-join invitation page |
| GET | `/assets/[file*]` | allowlisted module CSS/JS/font/hero and hash-named SVGs |
| POST | `/api/bootstrap` | deterministic guest bootstrap, requires page nonce header and cookie |
| POST | `/api/heartbeat` | authenticated, CSRF-protected, throttled presence |
| GET | `/api/state` | own engagement; optional `match_id` requires participation authorization |
| GET | `/api/profile` | own profile/history, `page` |
| POST | `/api/rename` | validate/persist name and avatar |
| GET | `/api/leaderboard` | `period=weekly` or `all`, `page`, own rank |
| GET | `/api/preview` | limited invitation preview by token; rate-limited |
| GET | `/api/health` | minimal boolean `available`, no paths, PIDs or tokens |
| POST | `/api/queue`, `/api/leave` | idempotent explicit queue enrollment or departure |
| POST | `/api/create`, `/api/join`, `/api/start` | private room, invitation join, host-only start |
| POST | `/api/answer` | membership/deadline checked answer acknowledgement only |
| POST | `/api/spectate`, `/api/rematch`, `/api/resume` | explicit authorized view/readiness operations |

Personal JSON uses `Cache-Control: no-store`, safe error codes/messages and appropriate status codes. CSRF uses the guest-derived header token; bootstrap uses a same-origin page challenge and a private HMAC key. Only identity token hashes live in SQLite; raw auth tokens are only in the HttpOnly cookie. All module cookies are path-scoped, SameSite=Lax, and production Secure. Host login cookies and PHP sessions are untouched.

## Production web and worker

`Deployment/nginx.conf` is a complete TLS virtual-host example to merge with existing host configuration. Replace the hostname, certificate and PHP socket paths. Preserve host module routing. Keep document root at `/srv/tools/public`, never `/srv/tools` or the module directory. No source-directory alias is needed for images. Disable access logging for invitation and API routes to prevent invitation tokens/query strings from entering logs; apply the same rule to proxy/CDN/APM services. The module sets `Referrer-Policy: no-referrer`, CSP, no-store personal responses and nosniff. Do not send invitation URLs to analytics.

For Apache, retain the host's public front-controller rewrite rules and public document root; add the equivalent request-size limit and exclude `/kalaqpar/r/` and `/kalaqpar/api/` requests from access logs. Do not replace the existing dirty `public/dev.htaccess` as part of this module installation. Essential assets are local and served by Pano.

`Deployment/kalaqpar.service` uses the actual verified command:

```sh
sudo install -m 0644 src/Modules/Kalaqpar/Deployment/kalaqpar.service /etc/systemd/system/kalaqpar.service
sudo systemctl daemon-reload
sudo systemctl enable --now kalaqpar
sudo systemctl status kalaqpar
sudo journalctl -u kalaqpar -f
```

The example assumes `/srv/tools`, PHP at `/usr/bin/php`, runtime in `/var/lib/kalaqpar`, and user/group `www-data`. Its restart policy is one second, normally below the technical-cancellation threshold. `ProtectSystem=strict` allows writes only to `/var/lib/kalaqpar`; set `KQ_DATABASE` accordingly. Stop a supervised worker with `systemctl stop kalaqpar`, since Restart=always intentionally restarts a worker stopped by the portable command. These system service commands were documented, not executed in the development environment.

Minimal health inspection:

```sh
curl -fsS https://tools.example.com/kalaqpar/api/health
```

Expect `ok:true` and `available:true`; an HTTP 200 with `available:false` means the worker is unavailable. Storage failures use safe HTTP 503. Monitor availability and `worker.log`/journald. `worker.log` has structured start/stop/error/lifecycle events with UTC time and numeric match/round IDs, never token values, cookies, names, request bodies or full URLs. API failures log only exception class to the host PHP error log. Rotate logs using a normal OS logrotate policy; no primary history is log data.

## Backup, restore, recovery and retention

A live WAL-compatible snapshot uses SQLite's own snapshot operation:

```sh
php pano kalaqpar kalaqpar:backup
```

This executes `VACUUM INTO` a uniquely named private `backups/game-UTCSTAMP-RANDOM.sqlite` beside the configured database. It works with a running worker, creates a consistent database including committed WAL data, does not overwrite another snapshot and prints the exact output path. Do not copy only `game.sqlite` while writers run. For large history, schedule backups when load is low; VACUUM can take time and needs free space.

Also back up `identity.key`, `.env` securely and the application release/assets. The private key makes simultaneous browser bootstrap deterministic. It does not replace tokens already hashed in the database. Store backups encrypted off-server with restricted access and a retention schedule; the application does not automatically delete backup files.

Restore during a maintenance window:

1. Stop the systemd worker and disable web writes (maintenance response or stop the relevant PHP-FPM pool). Verify all writers have stopped.
2. Preserve the current SQLite file and any WAL/SHM as a stopped, consistent rollback set. Move the complete stopped set aside; do not mix old WAL with a restored main file.
3. Copy the chosen snapshot to the configured database path and restore the matching `identity.key`. Restore ownership `www-data:www-data`, directory 0700, files 0600. Only after all old connections are closed, remove/move obsolete WAL/SHM files from the restored path.
4. Inspect with `sqlite3 /var/lib/kalaqpar/game.sqlite 'PRAGMA integrity_check;'` (expect `ok`). Use a schema-compatible application release, then run the explicit module migrations if needed. Migration and content commands do not erase game history.
5. Start web and worker. A snapshot with matches older than the outage threshold intentionally causes technical cancellation; never simulate elapsed rounds or manufacture competitive wins. Check health and review structured logs/history.

Rebuild cached account/match scores and finished result ranks/wins from the immutable ledger, with the worker stopped and web mutations disabled:

```sh
php pano kalaqpar kalaqpar:rebuild
```

This updates caches/results atomically, never the immutable events. Versioned schema migrations are forward-only. Before a release, snapshot the database; rollback application code only if it supports the migrated schema. Otherwise restore the whole compatible release/database/key set during maintenance. Do not run the legacy Guessing migration commands against this database.

Every second the worker removes queue entries absent for 15 seconds, transfers disconnected hosts after 30 seconds, and expires completely inactive non-playing rooms after 30 minutes. Active matches are never closed merely because everyone is offline. Expired auth tokens and old rate buckets are pruned. Identities, rooms, match rosters, ledgers and results are retained; foreign keys prevent accidental history deletion and immutable triggers reject event UPDATE/DELETE. No default archival job removes scores. Any future archival policy must retain an auditable full ledger or a separately specified, reconciled archival representation. Backups and log rotation require an operator policy.

## Asset maintenance

All production art is original vector work, source `Database/Content.php`. Rights and upstream versions: `Assets/THIRD_PARTY.md`. Bundled SVGs are ready; normal installation needs only the seed command. If intentionally editing content, regenerate bundled artwork from repository root, review it visually, then seed with the worker stopped:

```sh
php -r 'require "vendor/autoload.php"; $items=Src\Modules\Kalaqpar\Database\Content::catalogue(); foreach ($items as $i) { file_put_contents("src/Modules/Kalaqpar/Files/items/".$i["asset"].".svg", $i["svg"]); } file_put_contents("src/Modules/Kalaqpar/Assets/hero.svg", $items[0]["svg"]);'
php pano kalaqpar kalaqpar:seed
```

Do not put a categorized manifest in `public/`, HTML or frontend bundles. There is no future-round image mapping. Hashed public images are cacheable for one year; CSS/JS/font/hero for one hour. The active image has text fallback and alt text containing only its name.

## Actual integration changes

Everything game-specific is in this module. Outside it:

- `src/Foundation/DefaultFoundation.php`: resolve the existing `kalaqpar` key to `KalaqparModule`, correcting its old mapping to `GuessingModule`.
- `config/kalaqpar.php`: load dedicated module settings through the host's actual config loader.
- `.gitignore`: ignore only `src/Modules/Kalaqpar/Files/runtime/` and its private runtime artifacts.

No dependency upgrades, competing bootstrap, new framework, shared authentication changes, public asset rewrites or testing changes were made. Existing unrelated dirty app CSS/JS, RedJson views and `public/dev.htaccess` were preserved.

## Verification record and limits

Recorded on 2026-10-22:

- Read the complete English requirements and opened the overview JPG and all 15 PNG designs (including host/guest, both feedback variants, waiting, results, ranking and profile). Implemented the warm RTL cards, violet/yellow actions and local Persian typography from those references. A rendered browser comparison has not been performed.
- PHP syntax checks passed for module PHP, module/host config and foundation registration. Installed Windows Node's `--check` accepted the JavaScript. No tests or test scripts were authored, modified or run.
- This WSL PHP 8.5.4 initially had no PDO SQLite. Downloaded the matching Ubuntu extension package into `/tmp/kq-php`, without changing host PHP installation, and ran actual commands with `php -d extension=/tmp/kq-php/usr/lib/php/20250925/pdo_sqlite.so ...`. A normal deployment must enable `pdo_sqlite` itself. On this Ubuntu/PHP 8.5 installation the matching package is `php8.5-sqlite3` (`sudo apt-get install php8.5-sqlite3`); that system-wide installation was not performed. Other PHP versions/distributions need their matching extension package. No FFI database substitution is used.
- The native Pano migration command and rerun succeeded; rerunnable seed succeeded with 111 items/39 flying. The actual Pano development web server delivered the Persian page, scoped assets and live worker health. Three separate cookie jars bootstrapped distinct persistent guests.
- Three real HTTP guests joined a capacity-three private room and started via the current host. The worker progressed all 15 rounds without depending on polling and recorded exactly 45 settlements. With mostly deliberately unanswered rounds and one accepted wrong Fly, all three finished at -15 and each received a joint win. The all-negative result, one accepted answer acknowledgement without correctness, no extra timeout settlement for that wrong answer, and a later identical retry returning the original acceptance time were observed.
- Direct manual HTTP requests observed guest start rejection (403), missing-CSRF rejection (403), stale/deadline rejection (409), contradictory request reuse rejection (409), host transfer after heartbeat expiry, and explicit room departure.
- Direct public queue requests observed FIFO positions, idempotent join preserving `joined_at`, a formation deadline after the second guest, and offline expiration/canceled pre-start enrollment when manual sessions were left without heartbeats. A complete public match and a concurrent 30-player session have not yet been manually exercised; no polling/load benchmark is claimed.
- See the appended operational observations for backup, rebuild, worker lock and cancellation checks as completed in this workspace.

Remaining verification limits: no interactive browser was available for touch/keyboard, multi-tab, offline/reconnect rendering, full screenshot parity, every gameplay feedback variant or mobile/desktop visual acceptance. No real production systemd/Nginx/Apache/PHP-FPM rollout was performed. Thirty participants per match is a server capacity constraint, not a measured unlimited concurrency or throughput claim. SQLite remains single-writer; monitor real tick latency, polling load, WAL size and busy errors before increasing simultaneous matches. This implementation does not claim to prevent bots, multiple identities, shared invitations, collusion or differences in network latency.

Additional completed operational observations:

- A second native `kalaqpar:worker` invocation exited with the explicit lock-held error. The portable `kalaqpar:stop` command stopped the updated worker, and real HTTP health changed to `available:false`.
- `kalaqpar:backup` created a live SQLite snapshot. A copy restored to `/tmp/kq-restored.sqlite` opened through PDO with `PRAGMA integrity_check` = `ok` and 45 score events. This was a restored snapshot inspection, not a destructive restoration of the running database.
- `kalaqpar:rebuild` ran with the worker stopped. Subsequent HTTP profile/ranking showed the retained -15 score, one completed match, one joint win, zero accuracy and the three shared rank-one entries.
- A capacity-two private countdown admitted the third guest only to next-match waiting (two participants, one waiting, zero spectators). A spectator answer attempt returned 403. The stopped countdown was resumed after an outage exceeding ten seconds; the worker persisted `technical_cancelled` / `worker_outage`, zero events and zero results for that match. Cancellation during an open answer window and short crash recovery have not been manually exercised.
- The final implementation also refreshes home statistics about every 15 seconds and visible leaderboard data about every 10 seconds without resetting profile input drafts. Empty/unready prior members are displayed separately from the next roster candidates; waiting-list and spectator counts remain separate.

Final configuration lint: `systemd-analyze verify src/Modules/Kalaqpar/Deployment/kalaqpar.service` returned success outside the sandbox. The Windows-mounted workspace reports executable/world-writable source mode warnings; the documented `install -m 0644` sets the correct installed unit permissions. This is unit validation, not service deployment.
