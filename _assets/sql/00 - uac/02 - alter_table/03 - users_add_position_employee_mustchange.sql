-- ============================================================
-- ALTER: uac.users
-- Adds the columns used by User Access + the Dashboard:
--   * position_id            — the user's position (FK uac.positions)
--   * employee_id            — HR / plantilla employee number (free text)
--   * must_change_password   — forces a password change on next login
--
-- All statements are idempotent (IF NOT EXISTS / guarded), so this file
-- is safe to run on any environment, any number of times. It MUST run as
-- part of the alter_tables phase so a fresh DB import always has these
-- columns (the Dashboard's "Users by Position" query and the User Access
-- screen depend on them).
-- ============================================================

-- ── position_id ─────────────────────────────────────────────────────────────
ALTER TABLE uac.users
    ADD COLUMN IF NOT EXISTS position_id int4;

COMMENT ON COLUMN uac.users.position_id IS
    'The user''s position / job title, referencing uac.positions. NULL when unassigned.';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'users_position_id_fkey') THEN
        ALTER TABLE uac.users
            ADD CONSTRAINT users_position_id_fkey
            FOREIGN KEY (position_id) REFERENCES uac.positions (position_id)
            ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_users_position_id ON uac.users (position_id);

-- ── employee_id ─────────────────────────────────────────────────────────────
ALTER TABLE uac.users
    ADD COLUMN IF NOT EXISTS employee_id varchar(50),
    ADD COLUMN IF NOT EXISTS rc_code varchar(50);

COMMENT ON COLUMN uac.users.employee_id IS
    'HR / plantilla employee number (free text, optional).';

CREATE INDEX IF NOT EXISTS idx_users_employee_id ON uac.users (employee_id);

-- ── must_change_password ────────────────────────────────────────────────────
ALTER TABLE uac.users
    ADD COLUMN IF NOT EXISTS must_change_password boolean NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN uac.users.must_change_password IS
    'TRUE forces the user to change their password on next login '
    '(set by an admin password reset; cleared once they change it).';
