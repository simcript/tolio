# Kalaqpar on shared hosting

The owner requested this deployment mode on 2026-10-22. `KQ_PROGRESSION=shared` is the default. It replaces the original persistent-worker requirement **for this mode**. Ordinary authenticated, CSRF-protected POST requests run a short pass through the existing server engine. The browser polls via POST heartbeat and receives its authorized state in that response. No recurring cron, long-running PHP request, background process, shell execution from PHP, or external service is needed during gameplay.

Optional email/password recovery adds an independent once-per-minute `php pano kalaqpar kalaqpar:mail` task and outbound encrypted SMTP. Gameplay itself keeps the existing progression model. See the synchronized account sections in [README.md](README.md) and [README.fa.md](README.fa.md) for migration 6, SMTP secrets, availability, log exclusions and rollout. If recurring CLI or outbound SMTP is unavailable, keep `KQ_RECOVERY_ENABLED=false`; optional registration/login and guest play remain available.

## Hosting requirements

- PHP 8.2+ with PDO SQLite, JSON and Unicode PCRE, HTTPS, and the existing Pano front-controller rewrite support. Enable `pdo_sqlite` for both the website and the PHP binary used for installation.
- One application server, reliable `flock`, and writable **local disk** storage outside the actual document root. Network/NFS storage and multiple servers are unsupported. SQLite WAL requires processes on the same host: see the official SQLite [WAL documentation](https://www.sqlite.org/wal.html). PHP's [PDO SQLite documentation](https://www.php.net/manual/en/ref.pdo-sqlite.php) describes the required driver.
- A control-panel terminal, SSH, or a one-time scheduled CLI task to run the existing migration and seed commands. Gameplay does not require CLI after installation. There is deliberately no publicly exposed migration/seed/restore endpoint.
- Provider CPU, PHP process and I/O quotas sufficient for short polling. Capacity 30 is enforced by the game; it is not a provider throughput guarantee. Confirm that storage is local rather than assuming that a plan called “shared hosting” uses local storage.

## Install through the control panel

1. Upload the application outside the public directory, for example `/home/ACCOUNT/tools`. Use a domain/subdomain with document root `/home/ACCOUNT/tools/public`. Preserve the existing `public/.htaccess`, host routes and application configuration. Never expose the project root, `.env`, `vendor`, `src`, SQLite files, logs, backups or keys through HTTP.
2. Select PHP 8.2+ and enable PDO SQLite. Keep the existing root `.env`, and add/update these values (replace `ACCOUNT`):

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   KQ_PROGRESSION=shared
   KQ_MAINTENANCE=false
   KQ_DATABASE=/home/ACCOUNT/kalaqpar-private/game.sqlite
   KQ_SECURE_COOKIE=true
   ```

3. From the application directory, install the locked production dependencies and prepare content:

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader
   php -r 'echo implode(",", PDO::getAvailableDrivers()), PHP_EOL;'
   php pano kalaqpar kalaqpar:migrate
   php pano kalaqpar kalaqpar:seed
   ```

   The driver output must include `sqlite`. These commands are rerunnable; existing identities, scores and history are preserved. Migration 3 adds persistent cleanup timing. Seed provides 140 actual items and bundled illustrations, never demonstration players or results. See [CONTENT.md](CONTENT.md) for composition and upgrade checks. Existing disabled items stay disabled. Use the control panel's actual PHP executable when it differs from `php`.

4. The PHP website user must own or have private write access to `/home/ACCOUNT/kalaqpar-private` including WAL/SHM, `worker.lock`, `identity.key` and `worker.log`. Directory 0700 and files 0600 work when the CLI and web run as the same hosting account. Do not use 0777. Configure invitation/API access-log exclusions where the host allows it; avoid logging invitation URLs in analytics, proxy or APM services.
5. Open `https://YOUR-DOMAIN/kalaqpar/`. `/kalaqpar/api/health` should report `available:true` and `progression_mode:"shared"`. Health is a read-only readiness check in shared mode, not a claim that a daemon is running. Open two independent browsers, join the same private room and start through the host, or join the public queue.

Do **not** run `kalaqpar:worker` or schedule repeated worker invocations in shared mode. The command explicitly rejects the wrong mode.

### If SSH or Composer is unavailable

Install dependencies locally with the locked Composer command above on a compatible PHP system, then upload the application including `vendor`. If the control panel has one-time scheduled commands, run the following **once** using its documented PHP executable and actual account paths, then remove the schedule after it succeeds:

```sh
cd /home/ACCOUNT/tools && /ACTUAL/PHP/BINARY pano kalaqpar kalaqpar:migrate && /ACTUAL/PHP/BINARY pano kalaqpar kalaqpar:seed
```

Inspect the panel's output for success. The PHP binary path is provider-specific; no universal cPanel path is assumed. If the provider offers neither terminal nor one-time CLI commands, prepare a new empty seeded database locally, upload it with all writers stopped and enable the site afterward. For an **existing** game database, use the backup/restore procedure in `OPERATIONS.md` and perform its migrations on a compatible local SQLite/PHP release before upload; never replace it with an empty database. Match `KQ_DATABASE` to the uploaded private location. The identity key must accompany an existing deployment. Do not upload a live database by copying only its main file while WAL writers are active.

### Fixed `public_html` document root

Prefer a dedicated subdomain whose document root can be set to the application's `public/`. If the host only serves `public_html`, keep the source at `/home/ACCOUNT/tools`, copy the existing contents of `tools/public/` (including `.htaccess`) into `public_html`, and change **only the deployed** `public_html/index.php` base-path assignment to:

```php
$basePath = dirname(__DIR__) . '/tools/';
```

Keep the rest of the native front controller intact. Merge with any existing website deliberately; do not overwrite its entry point blindly. There is no repository-wide bootstrap change for this hosting-specific arrangement. The database connection also rejects storage inside the web server's actual `DOCUMENT_ROOT`. Keep `.env` and source outside `public_html`.

## Timing and recovery

- The server chooses items, timestamps deadlines after obtaining SQLite locks, orders accepted answers and settles the immutable ledger. Client timestamps never affect scoring. Every player still sees the same round and deadline. GET state, preview and health never progress games.
- Authenticated POST operations attempt a progression pass under the same nonblocking `worker.lock` used by CLI maintenance. Passes are coalesced to at most one per 200ms across all browsers/tabs, not one per player. Presence writes are still throttled to three seconds; global cleanup timing persists across requests. SQLite transactions and uniqueness constraints protect answers, capacity, host transfer, settlements, results and wins.
- During visible gameplay, the frontend requests updates about every 300ms, plus request time. Lobby updates are about one second; idle pages five seconds; hidden pages four seconds. It keeps one poll in flight, backs off on failures and retains the same answer request ID across retries. An answer acknowledgement does not disclose correctness.
- A phase changes on the next successful authenticated POST after its due time. Round deadlines never extend to accept late answers. New rounds start with a fresh server-timed answer window; the engine never invents a series of unseen catch-up rounds. Delayed settlement may lengthen the wait for feedback or the next round. Provider/network latency can therefore affect reaction-game fairness and smoothness.
- With no requests, no process runs. After more than ten seconds without successful progression, the next POST technically cancels stale active matches **before** advancing them: settled scores stay, the open round is not penalized, and no competitive wins/completed results are created. Previously accepted answers remain stored. If everyone closes or suspends their browser, independent background progression is unavailable in this mode. A remaining connected player can continue to drive progression for departed/offline participants.
- `worker.log` remains the private structured lifecycle log in both modes. Health reports shared readiness based on content and maintenance state. A database/lock/log failure returns a safe error. An idle heartbeat is not presented as a running daemon. `KQ_MAINTENANCE=true` rejects authenticated mutations and reports unavailable while allowing read-only diagnostics; use the hosting control panel to block traffic for database restoration and schema maintenance.

## Upgrade and optional worker mode

Before switching an existing deployment, close traffic, stop its old worker (`php pano kalaqpar kalaqpar:stop`; stop the systemd/Supervisor service too if it restarts workers), take a backup, upload the changed module and configure shared mode. Run `kalaqpar:migrate` and `kalaqpar:seed`, then reopen traffic. The layout versions the new JavaScript URL so old cached polling code is refreshed. Wait for the old process to exit before migration; do not run both progression modes together. Existing games affected by a long upgrade correctly become technical cancellations.

To use independent progression later, set `KQ_PROGRESSION=worker` during maintenance and run:

```sh
php pano kalaqpar kalaqpar:worker
```

The existing service template, health freshness, graceful stop and outage recovery apply in that mode. `KQ_MAINTENANCE` must be false when reopening. Backup and ledger rebuild use the same commands in either mode:

```sh
php pano kalaqpar kalaqpar:backup
php pano kalaqpar kalaqpar:rebuild
```

Rebuild requires all web writes blocked and any worker stopped; preserve the identity key with backups. Full restore, permissions and WAL handling are in `OPERATIONS.md`.

## Verification and limitations

This change does not author or modify frontend/backend tests, fixtures, test scripts, testing dependencies or existing testing infrastructure. Syntax checks and direct manual runtime observations are recorded below after execution. No hosting account or provider credentials were supplied; deployment on a real shared-host provider, browser visual acceptance and 30-player throughput remain unverified.

Observed manually in this workspace on 2026-10-22:

- All module PHP files passed `php -l`; JavaScript passed `node --check`; scoped `git diff --check` passed. Existing tests and testing configuration remained unchanged.
- The Windows-mounted default runtime database returned SQLite `disk I/O error` on connection. It was not replaced. On Linux local disk, `/tmp/kalaqpar-shared/game.sqlite`, the native migration and rerun succeeded through version 3; seed succeeded. This PHP installation still needs its normal PDO SQLite extension enabled permanently; manual commands loaded the previously extracted matching extension explicitly.
- The native Pano development server ran without a CLI worker. The health endpoint returned `available:true, progression_mode:"shared"`. Two independent cookie jars bootstrapped distinct persistent guests. The shared-mode worker command exited with its explicit configuration error instead of starting a daemon.
- Both guests joined a capacity-two private room. Inactivity transferred the host; start attempts by the former host returned 403. Starting through the current host produced countdown, then a POST heartbeat began the first common round. Two Skip requests were accepted with distinct server acceptance times and no correctness in their acknowledgements. The item was an owl, so both wrong Skips settled once at -1 when the next heartbeat crossed the deadline. Exactly two ledger events existed.
- After a real gap exceeding ten seconds, GET still returned the prior feedback phase. The next authenticated POST persisted technical cancellation with the historical reason code `worker_outage` (this denotes a progression gap in shared mode too). Both -1 scores stayed, results/wins were absent, and retrying the earlier answer returned its original acceptance time. A heartbeat missing CSRF was rejected.
- The same guests left the private room and entered public FIFO at positions 1 and 2. With manual intervening heartbeats, the twelve-second formation period produced a public countdown and then round 1 with two participants. After another real idle gap, the next POST technically cancelled that open round with score zero for both; no new ledger penalty or competitive result was created. A complete fifteen-round game in shared mode was not manually played.
- Direct SQLite inspection returned `integrity_check = ok`, two retained ledger events and zero competitive results after the private cancellation. No browser automation or other test code was created to obtain these observations.

Provider deployment, interactive browser rendering/reconnect/touch/multi-tab acceptance, many simultaneous matches and thirty-player concurrency remain unverified. The original full fifteen-round worker-mode observations remain in `OPERATIONS.md`; they are not presented as a completed shared-mode game.
