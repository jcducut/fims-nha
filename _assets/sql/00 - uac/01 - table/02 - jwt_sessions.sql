-- =============================================================================
-- UAC: JWT Session Registry
-- Purpose: Track every JWT issued by FIMS so tokens can be validated
--          server-side (revocation, discrepancy detection, audit trail).
-- Consumers today:
--   /LIBIS/api/login       — INSERT on successful login
--   /LIBIS/api/logout      — UPDATE ... SET revoked_at
--   /LIBIS/api/*           — SELECT + 5 discrepancy checks on every request
-- Strategy: CREATE IF NOT EXISTS — safe migration, no DROP
-- =============================================================================

SET search_path TO uac, public;

-- =============================================================================
-- SEQUENCE
-- =============================================================================
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'uac' AND sequencename = 'jwt_sessions_session_id_seq') THEN
        CREATE SEQUENCE uac.jwt_sessions_session_id_seq
            INCREMENT 1 MINVALUE 1 MAXVALUE 2147483647 START 1 CACHE 1;
    END IF;
END $$;

-- =============================================================================
-- TABLE: uac.jwt_sessions
-- =============================================================================
CREATE TABLE IF NOT EXISTS uac.jwt_sessions (
    session_id      INT4         NOT NULL DEFAULT nextval('uac.jwt_sessions_session_id_seq'),
    token_hash      CHAR(64)     NOT NULL,                                -- SHA-256 hex of the raw JWT
    user_id         INT4         NOT NULL,                                -- FK -> uac.users
    system          VARCHAR(20)  NOT NULL DEFAULT 'LIBIS',                -- issuer surface: LIBIS, FIMS, ...
    issued_at       TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,      -- server clock when INSERTed
    expires_at      TIMESTAMPTZ  NOT NULL,                                -- MUST equal to_timestamp(JWT.exp)
    client_ip       INET,                                                 -- audit — best-effort from REMOTE_ADDR / X-Forwarded-For
    user_agent      TEXT,                                                 -- audit — from User-Agent header
    revoked_at      TIMESTAMPTZ,                                          -- NULL = active; non-NULL = terminated
    revoke_reason   VARCHAR(100),                                         -- 'logout' | 'admin-terminate' | 'discrepancy' | 'expired' | 'security-event'
    CONSTRAINT jwt_sessions_pkey       PRIMARY KEY (session_id),
    CONSTRAINT jwt_sessions_token_uk   UNIQUE (token_hash),
    CONSTRAINT jwt_sessions_user_fk    FOREIGN KEY (user_id) REFERENCES uac.users(user_id)
);
-- Tie the sequence to the column so pg_get_serial_sequence() resolves it and
-- reset_all_sequences.sql realigns it after restores / explicit-id copies.
-- Without this, nextval() can lag MAX(session_id) → jwt_sessions_pkey violation.
ALTER SEQUENCE uac.jwt_sessions_session_id_seq OWNED BY uac.jwt_sessions.session_id;

COMMENT ON TABLE  uac.jwt_sessions IS
    'Server-side registry of issued JWTs. Enables stateful revocation, discrepancy detection, and an audit trail. token_hash is the SHA-256 (hex) of the raw JWT — never the token itself.';
COMMENT ON COLUMN uac.jwt_sessions.token_hash    IS 'SHA-256 hex digest of the raw JWT. Never store the token itself — if this table leaks, hashes are not directly usable.';
COMMENT ON COLUMN uac.jwt_sessions.expires_at    IS 'Belt-and-braces expiry. MUST mirror the JWT exp claim; discrepancy = tampering.';
COMMENT ON COLUMN uac.jwt_sessions.revoked_at    IS 'NULL = active. Set on logout, admin-terminate, or when a discrepancy is caught.';
COMMENT ON COLUMN uac.jwt_sessions.revoke_reason IS 'Categorical label. Free text below 100 chars.';

-- =============================================================================
-- INDEXES
-- token_hash is the primary lookup on EVERY authenticated request → keep the
-- covering index tight. UNIQUE constraint above already creates a b-tree.
-- =============================================================================
CREATE INDEX IF NOT EXISTS idx_jwt_sessions_user_active
    ON uac.jwt_sessions (user_id)
 WHERE revoked_at IS NULL;                                              -- partial: "active sessions for user X"

CREATE INDEX IF NOT EXISTS idx_jwt_sessions_expires
    ON uac.jwt_sessions (expires_at);                                   -- housekeeping / purge queries
