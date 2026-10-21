CREATE TABLE kalaqpar_users (
 id INTEGER PRIMARY KEY, public_id TEXT NOT NULL UNIQUE, name TEXT NOT NULL,
 avatar INTEGER NOT NULL CHECK(avatar BETWEEN 0 AND 5), created_at INTEGER NOT NULL,
 seen_at INTEGER NOT NULL, total_score INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE kalaqpar_tokens (
 hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES kalaqpar_users(id),
 expires_at INTEGER NOT NULL
);
CREATE INDEX kalaqpar_tokens_expiry ON kalaqpar_tokens(expires_at);
CREATE TABLE kalaqpar_items (
 id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE, asset TEXT NOT NULL UNIQUE,
 can_fly INTEGER NOT NULL CHECK(can_fly IN (0,1)), enabled INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE kalaqpar_rooms (
 id INTEGER PRIMARY KEY, mode TEXT NOT NULL CHECK(mode IN ('public','private')),
 name TEXT NOT NULL, capacity INTEGER NOT NULL CHECK(capacity BETWEEN 2 AND 30),
 invitation TEXT UNIQUE, host_id INTEGER REFERENCES kalaqpar_users(id),
 phase TEXT NOT NULL DEFAULT 'lobby', current_match_id INTEGER REFERENCES kalaqpar_matches(id),
 version INTEGER NOT NULL DEFAULT 1, created_at INTEGER NOT NULL, empty_since INTEGER
);
CREATE TABLE kalaqpar_matches (
 id INTEGER PRIMARY KEY, room_id INTEGER NOT NULL REFERENCES kalaqpar_rooms(id),
 phase TEXT NOT NULL, number INTEGER NOT NULL DEFAULT 0, total INTEGER NOT NULL,
 settings TEXT NOT NULL, starts_at INTEGER NOT NULL, next_at INTEGER NOT NULL,
 created_at INTEGER NOT NULL, ended_at INTEGER, cancel_reason TEXT
);
CREATE INDEX kalaqpar_matches_due ON kalaqpar_matches(phase,next_at);
CREATE TABLE kalaqpar_engagements (
 user_id INTEGER PRIMARY KEY REFERENCES kalaqpar_users(id),
 kind TEXT NOT NULL CHECK(kind IN ('queue','lobby','waiting','match')),
 room_id INTEGER REFERENCES kalaqpar_rooms(id), match_id INTEGER REFERENCES kalaqpar_matches(id),
 joined_at INTEGER NOT NULL, priority INTEGER NOT NULL DEFAULT 1,
 spectating INTEGER NOT NULL DEFAULT 0, departed INTEGER NOT NULL DEFAULT 0, ready INTEGER NOT NULL DEFAULT 1,
 CHECK((kind='queue' AND room_id IS NULL AND match_id IS NULL) OR
       (kind IN ('lobby','waiting') AND room_id IS NOT NULL AND match_id IS NULL) OR
       (kind='match' AND room_id IS NOT NULL AND match_id IS NOT NULL))
);
CREATE INDEX kalaqpar_engagement_room ON kalaqpar_engagements(room_id,kind,priority,joined_at);
CREATE INDEX kalaqpar_queue_fifo ON kalaqpar_engagements(kind,joined_at,user_id);
CREATE TABLE kalaqpar_match_players (
 match_id INTEGER NOT NULL REFERENCES kalaqpar_matches(id),
 user_id INTEGER NOT NULL REFERENCES kalaqpar_users(id), score INTEGER NOT NULL DEFAULT 0,
 PRIMARY KEY(match_id,user_id)
);
CREATE TABLE kalaqpar_rounds (
 id INTEGER PRIMARY KEY, match_id INTEGER NOT NULL REFERENCES kalaqpar_matches(id),
 number INTEGER NOT NULL, item_id INTEGER NOT NULL REFERENCES kalaqpar_items(id),
 starts_at INTEGER NOT NULL, deadline_at INTEGER NOT NULL, settled_at INTEGER,
 UNIQUE(match_id,number)
);
CREATE INDEX kalaqpar_round_match ON kalaqpar_rounds(match_id,number);
CREATE TABLE kalaqpar_responses (
 sequence INTEGER PRIMARY KEY AUTOINCREMENT, round_id INTEGER NOT NULL REFERENCES kalaqpar_rounds(id),
 user_id INTEGER NOT NULL REFERENCES kalaqpar_users(id), action TEXT NOT NULL CHECK(action IN ('fly','skip')),
 request_id TEXT NOT NULL, accepted_at INTEGER NOT NULL, elapsed_ms INTEGER,
 UNIQUE(round_id,user_id), UNIQUE(user_id,request_id)
);
CREATE TABLE kalaqpar_score_events (
 id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES kalaqpar_users(id),
 match_id INTEGER NOT NULL REFERENCES kalaqpar_matches(id), round_id INTEGER NOT NULL REFERENCES kalaqpar_rounds(id),
 delta INTEGER NOT NULL CHECK(delta BETWEEN -1 AND 1), correct INTEGER NOT NULL CHECK(correct IN (0,1)),
 reason TEXT NOT NULL, created_at INTEGER NOT NULL, UNIQUE(round_id,user_id)
);
CREATE INDEX kalaqpar_score_user_time ON kalaqpar_score_events(user_id,created_at);
CREATE INDEX kalaqpar_score_time ON kalaqpar_score_events(created_at,user_id);
CREATE TRIGGER kalaqpar_ledger_no_update BEFORE UPDATE ON kalaqpar_score_events BEGIN SELECT RAISE(ABORT,'immutable ledger'); END;
CREATE TRIGGER kalaqpar_ledger_no_delete BEFORE DELETE ON kalaqpar_score_events BEGIN SELECT RAISE(ABORT,'immutable ledger'); END;
CREATE TABLE kalaqpar_results (
 match_id INTEGER NOT NULL REFERENCES kalaqpar_matches(id), user_id INTEGER NOT NULL REFERENCES kalaqpar_users(id),
 score INTEGER NOT NULL, rank INTEGER NOT NULL, won INTEGER NOT NULL CHECK(won IN (0,1)),
 PRIMARY KEY(match_id,user_id)
);
CREATE TABLE kalaqpar_worker_status (id INTEGER PRIMARY KEY CHECK(id=1), heartbeat INTEGER NOT NULL DEFAULT 0,
 running INTEGER NOT NULL DEFAULT 0, formation_at INTEGER);
INSERT INTO kalaqpar_worker_status(id) VALUES (1);
CREATE TABLE kalaqpar_limits (subject TEXT NOT NULL, scope TEXT NOT NULL, bucket INTEGER NOT NULL,
 hits INTEGER NOT NULL, PRIMARY KEY(subject,scope,bucket));
