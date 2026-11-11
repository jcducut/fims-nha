-- ============================================================
-- ALTER: uac.users
-- Replaces the free-text rc_code column (added in
-- 03 - users_add_position_employee_mustchange.sql) with a proper FK:
--   * rc_id — references fims.responsibility_code(rc_id)
--
-- rc_code was a loose copy of fims.responsibility_code.rc_code with no
-- referential integrity. rc_id lets the user's assignment follow renames /
-- corrections to the responsibility center without drifting out of sync.
--
-- All statements are idempotent (IF NOT EXISTS / guarded), so this file is
-- safe to run on any environment, any number of times.
-- ============================================================

-- ── rc_id ────────────────────────────────────────────────────────────────
ALTER TABLE uac.users
    ADD COLUMN IF NOT EXISTS rc_id varchar(10);

COMMENT ON COLUMN uac.users.rc_id IS
    'The user''s responsibility center, referencing fims.responsibility_code.rc_id. NULL when unassigned.';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'users_rc_id_fkey') THEN
        ALTER TABLE uac.users
            ADD CONSTRAINT users_rc_id_fkey
            FOREIGN KEY (rc_id) REFERENCES fims.responsibility_code (rc_id)
            ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_users_rc_id ON uac.users (rc_id);

-- Best-effort backfill: carry over any existing rc_code value by matching it
-- to its responsibility_code row, before the column is dropped.
UPDATE uac.users u
SET rc_id = rc.rc_id
FROM fims.responsibility_code rc
WHERE u.rc_code IS NOT NULL
  AND u.rc_id IS NULL
  AND rc.rc_code = u.rc_code;

-- ── drop rc_code ─────────────────────────────────────────────────────────
ALTER TABLE uac.users
    DROP COLUMN IF EXISTS rc_code;
