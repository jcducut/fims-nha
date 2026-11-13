-- ============================================================
-- ALTER: fims.budget_req
-- Moves fims.budget_req from "one header per fiscal year, system-wide" to
-- "one header per (fiscal_yr, rc_id) pair" — every Responsibility Center
-- now gets/creates its own Draft header for a fiscal year instead of
-- colliding into one shared row (see BudgetReqService::addProgram() /
-- fetchHeaderByFiscalYearAndRc()).
--
--   * rc_id — FK fims.responsibility_code(rc_id), NOT NULL. Every budget
--     request belongs to exactly one RC, auto-detected server-side from
--     the creating user's own uac.users.rc_id (see
--     GlobalService::getUserRcId()) — there is no manual RC picker.
--
-- Dev data only, no preservation needed (same precedent as "04 -
-- budget_req_fiscal_yr_unique_drop_program_cols.sql", which collapsed
-- duplicate-fiscal-year rows the same way): pre-existing budget_req rows
-- predate rc_id and have no RC to backfill from, so any row still NULL
-- after the column is added is removed (cascades to its
-- budget_req_program/budget_req_project/budget_line_items children)
-- rather than guessed at. No-op on re-runs once real rc_id-bearing rows
-- exist.
--
-- Idempotent (IF EXISTS / IF NOT EXISTS guarded), safe to run any number
-- of times.
-- ============================================================

ALTER TABLE fims.budget_req
    ADD COLUMN IF NOT EXISTS rc_id VARCHAR(10);

DELETE FROM fims.budget_req WHERE rc_id IS NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'budget_req_rc_id_fkey') THEN
        ALTER TABLE fims.budget_req
            ADD CONSTRAINT budget_req_rc_id_fkey
            FOREIGN KEY (rc_id) REFERENCES fims.responsibility_code (rc_id);
    END IF;
END $$;

ALTER TABLE fims.budget_req
    ALTER COLUMN rc_id SET NOT NULL;

ALTER TABLE fims.budget_req
    DROP CONSTRAINT IF EXISTS uq_budget_req_fiscal_yr;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_budget_req_fiscal_yr_rc_id') THEN
        ALTER TABLE fims.budget_req
            ADD CONSTRAINT uq_budget_req_fiscal_yr_rc_id UNIQUE (fiscal_yr, rc_id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_budget_req_rc_id ON fims.budget_req (rc_id);
