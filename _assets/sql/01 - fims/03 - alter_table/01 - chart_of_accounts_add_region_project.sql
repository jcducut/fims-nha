-- ============================================================
-- ALTER: fims.chart_of_accounts
-- Adds the columns used by the Chart of Accounts page so an account can
-- optionally be scoped to a region and/or a project:
--   * region_code   — FK fims.responsibility_code(rc_code), i.e. the RCA
--                     (Responsibility Center/Accounting) code; NULL = not
--                     scoped to a specific responsibility center
--   * project_code  — FK fims.project, NULL = not project-specific
--
-- Both are idempotent (IF NOT EXISTS / guarded), safe to run any number
-- of times, and must run after fims.responsibility_code and fims.project
-- exist (phase 01 - table).
-- ============================================================

ALTER TABLE fims.chart_of_accounts
    ADD COLUMN IF NOT EXISTS region_code VARCHAR(30);

ALTER TABLE fims.chart_of_accounts
    ALTER COLUMN region_code TYPE VARCHAR(30);

COMMENT ON COLUMN fims.chart_of_accounts.region_code IS
    'RCA (Responsibility Center/Accounting) code for this account, referencing fims.responsibility_code.rc_code. NULL = not scoped to a specific responsibility center.';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chart_of_accounts_region_code_fkey') THEN
        ALTER TABLE fims.chart_of_accounts
            ADD CONSTRAINT chart_of_accounts_region_code_fkey
            FOREIGN KEY (region_code) REFERENCES fims.responsibility_code (rc_code)
            ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_chart_of_accounts_region_code ON fims.chart_of_accounts (region_code);

ALTER TABLE fims.chart_of_accounts
    ADD COLUMN IF NOT EXISTS project_code VARCHAR(30);

COMMENT ON COLUMN fims.chart_of_accounts.project_code IS
    'Optional project scope for this account, referencing fims.project. NULL = not project-specific.';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chart_of_accounts_project_code_fkey') THEN
        ALTER TABLE fims.chart_of_accounts
            ADD CONSTRAINT chart_of_accounts_project_code_fkey
            FOREIGN KEY (project_code) REFERENCES fims.project (project_code)
            ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_chart_of_accounts_project_code ON fims.chart_of_accounts (project_code);
