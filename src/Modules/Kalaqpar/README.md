# Kalaqpar (کلاغ‌پر)

A real-time multiplayer Persian word-racing game as a native Pano module: room
based, mobile-first, fully RTL. The secret word is revealed letter by letter
every 12 seconds; the first player to type it correctly wins the round. The
earlier you solve, the more points you earn — so the classic "solve before the
crowd" tension is the whole game.

Kalaghpar ("crow race") is the traditional Persian line game where the loser of
the rock-paper-scissors race is chased by the winner; here every round is a
race to read the half-shown word before someone else does.

## Game rules

| Rule | Value (overridable via config/env) |
| --- | --- |
| Rounds per game | 5 default, 1–9 (`KALAQPAR_DEFAULT_ROUNDS`, `KALAQPAR_MAX_ROUNDS`) |
| Letter reveal interval | 12 s (`KALAQPAR_REVEAL_SECONDS`) |
| Round deadline | word length × reveal interval + grace (8 s), capped at 180 s |
| Base points for a solve | `max(4, 16 − 3 × revealed_letters)` |
| Streak bonus | `min(12, 3 × (streak − 1))` for consecutive wins |
| Wrong guesses | 10 per round, max 1 guess every 2 s (`KALAQPAR_GUESS_COOLDOWN`, `KALAQPAR_WRONG_GUESS_LIMIT`) |
| Lobby retirement | idle host-less lobbies close after 30 min (`KALAQPAR_LOBBY_TIMEOUT`) |
| Retention | finished rooms/rounds/events pruned after 14 days (`kalaqpar:prune`) |

A round ends the moment a player guesses correctly (win) or at the deadline
(timeout — nobody scores). The engine picks a fresh word each round and never
repeats a word inside one game; a new round starts automatically. When the last
round finishes the room closes as `completed`; if everyone leaves the game the
room closes as `empty`.

## Architecture

```
src/Modules/Kalaqpar
├── Commands/        kalaqpar:migrate | seed | serve | prune (php pano ...)
├── Database/        Connection (PDO/SQLite), Migrator, WordSeeder
├── Exceptions/      KalaqparException → stable JSON error codes
├── Files/           words.json (135-word pack), kalaqpar.sqlite (runtime)
├── Handlers/        LobbyHandler (pages), ApiHandler (JSON API)
├── Repositories/    Guest/Room/RoundRepository (all writes in transactions)
├── Services/        GameEngine, GameRules, GameState, GameOptions,
│                    EventLog, GuestService, RateLimiter
├── Views/           layout/home/room/error (Persian RTL)
└── KalaqparModule   routes + CLI registration
```

Key properties:

- **Everything is time-injected.** Engine entry points take `int $now`
  explicitly, so the whole lifecycle is deterministic under test.
- **One sequenced world.** Every room/event mutation re-checks its
  precondition inside a `BEGIN IMMEDIATE` transaction (SQLite single-writer
  serialization) — multiple workers and the lazy page-load pulse never
  double-process a round.
- **Append-only bookkeeping.** Scores, guesses and events are insert-only;
  `state_version` on the room is a monotonic cursor for polling.
- **Module-scoped isolation.** Own SQLite database, tables (`kalaqpar_*`),
  cookie (`kalaqpar_guest`, HttpOnly, token stored SHA-256-hashed), CSRF
  (`X-Kalaqpar-CSRF` header rendered into pages), rate limiters and config
  (`config/kalaqpar.php` with `KALAQPAR_*` envs). It never reads or writes any
  host cookie/session — the host project has no sessions itself, so guest
  identity is deliberately module-local.
- **Word secrecy.** The secret word never crosses the API until the round
  ends; state and event payloads carry only the revealed prefix.

## Running it

Setup (once):

```bash
php pano kalaqpar kalaqpar:migrate   # create the module tables
php pano kalaqpar kalaqpar:seed      # load words.json into kalaqpar_words
```

Begin a game by opening `<host>/kalaqpar/` in two browsers; the first player
creates a room, shares the short code, and starts the round.

Keep the game moving with the worker (reveals/timeouts/round chaining), which
you may run together with a CLI dev server:

```bash
php -S localhost:8000 -t public
php pano kalaqpar kalaqpar:serve                 # run until Ctrl+C
php pano kalaqpar kalaqpar:serve --max-ticks=2   # bounded smoke run
php pano kalaqpar kalaqpar:prune                 # drop rooms older than retention
```

Notes on the worker: without it, state polls (`GET /kalaqpar/state`) still
trigger a lazy engine pulse, so pages stay playable; the CLI worker is the
low-power way to keep many rooms advancing. Because the framework folds one
`--option` into the command URL, combine `--interval` with `--max-ticks` by
using `KALAQPAR_WORKER_TICKS` for the tick cap instead (see `env.example`).
`php pano kalaqpar kalaqpar:serve` writes health to `Files/worker-health.json`.

## API (all JSON, guest + CSRF gated)

| Method & path | Purpose |
| --- | --- |
| `GET /kalaqpar/` | Lobby page (issues guest identity + CSRF token) |
| `GET /kalaqpar/room/[code]` | Room page |
| `GET /kalaqpar/rooms` | Open rooms for the lobby |
| `POST /kalaqpar/rooms` | Create room `{name?, rounds?}` → 201 |
| `POST /kalaqpar/rooms/join` | Join room `{room}` |
| `POST /kalaqpar/rooms/leave` | Leave room (host handover; empty ⇒ close) |
| `POST /kalaqpar/rooms/start` | Host only, starts round 1 |
| `POST /kalaqpar/guess` | `{room, guess}`; wrong guesses budgeted + cooled |
| `POST /kalaqpar/profile` | Change display name (2–24 chars) |
| `GET /kalaqpar/state?room=X&after=N` | Poll state + events after cursor `N` |
| `GET /kalaqpar/leaderboard` | Top 10 lifetime scorers |

Every failure is a stable JSON envelope: `{"ok":false,"error":"<code>", …}` —
`validation`, `csrf`, `guest_required`, `not_member`, `rate_limited`,
`too_late`, `conflict`, `not_found`.

## Persistence & retention

Data lives in a module-private SQLite file outside `public/`
(`src/Modules/Kalaqpar/Files/kalaqpar.sqlite`; absolute override
`KALAQPAR_DATABASE`). WAL mode + busy timeout keep concurrent reads cheap;
writers serialize on SQLite's single-writer transactions. `kalaqpar:prune`
removes rooms that finished/closed more than `KALAQPAR_RETENTION_DAYS` ago,
together with their rounds, guesses, scores and event logs.

## Tests

PHPUnit covers the pure rules, engine lifecycle, repositories, guest/CSRF/rate
limit behavior and full handler flows; each test boots against an isolated
temporary SQLite database and word list. The project-wide integration script
also dispatches every module page:

```bash
php vendor\phpunit\phpunit --bootstrap vendor/autoload.php tests/Kalaqpar
php tests/apps-smoke.php
```
