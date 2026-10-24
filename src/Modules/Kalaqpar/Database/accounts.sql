-- Migration 6: additive email credentials; no user, phone, score or relationship changes.
CREATE TABLE kalaqpar_email_accounts (
 user_id INTEGER PRIMARY KEY REFERENCES kalaqpar_users(id),
 email TEXT NOT NULL, email_canonical TEXT NOT NULL COLLATE BINARY UNIQUE
 CHECK(email_canonical=lower(trim(email_canonical)) AND length(email_canonical) BETWEEN 3 AND 254),
 password_hash TEXT NOT NULL, created_at INTEGER NOT NULL,
 password_changed_at INTEGER NOT NULL, email_verified_at INTEGER DEFAULT NULL CHECK(email_verified_at IS NULL)
);
CREATE TABLE kalaqpar_retired_tokens (hash TEXT PRIMARY KEY, retired_at INTEGER NOT NULL);
CREATE TABLE kalaqpar_password_resets (
 hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES kalaqpar_email_accounts(user_id),
 expires_at INTEGER NOT NULL, created_at INTEGER NOT NULL
);
CREATE INDEX kalaqpar_resets_user ON kalaqpar_password_resets(user_id);
CREATE INDEX kalaqpar_resets_expiry ON kalaqpar_password_resets(expires_at);
-- Only the normalized address is queued; reset secrets exist only during delivery.
CREATE TABLE kalaqpar_recovery_queue (
 email_canonical TEXT PRIMARY KEY, requested_at INTEGER NOT NULL, attempts INTEGER NOT NULL DEFAULT 0,
 next_at INTEGER NOT NULL
);
CREATE TABLE kalaqpar_mail_status (id INTEGER PRIMARY KEY CHECK(id=1), heartbeat INTEGER NOT NULL DEFAULT 0);
INSERT INTO kalaqpar_mail_status(id) VALUES (1);
