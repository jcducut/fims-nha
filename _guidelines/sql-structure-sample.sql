-- ============================================================================
-- SQL STRUCTURE SAMPLE — FIMS NHA
-- ----------------------------------------------------------------------------
-- Reference/template only. This file lives in `_guidelines/` ON PURPOSE so the
-- installer (`install_system.js`, which walks `_assets/sql/`) never runs it.
-- Sample names (fmd.sample_budget_allocations, etc.) are fictional.
--
-- FORMAT (house style):
--   1. Banner comment with the table name in UPPERCASE.
--   2. CREATE TABLE IF NOT EXISTS <table>();          -- empty shell
--   3. ALTER TABLE <table> ADD COLUMN IF NOT EXISTS   -- every column, one per line
--   4. -- <table> indexes                             -- CREATE INDEX IF NOT EXISTS
--
-- Why: every statement is idempotent, so the file can be re-run safely, and a
-- new column is just one more `ADD COLUMN IF NOT EXISTS` line.
--
-- Rules for every file:
--   * Schema-qualify names (fmd.table, never just table).
--   * Idempotent: IF NOT EXISTS / DROP ... IF EXISTS / ON CONFLICT.
--   * File name = next unused number in its folder, e.g. `07 - fmd_sample_budget_allocations.sql`.
--   * Installer runs with ON_ERROR_STOP=1: one error stops the whole install.
-- ============================================================================


-- ============================================================================
-- SECTION 1 — TABLE
-- Location : _assets/sql/01 - schema_table_structure/02 - FMD/01 - table/
-- File     : 07 - fmd_sample_budget_allocations.sql
-- ============================================================================

-- =============================================
-- SAMPLE_BUDGET_ALLOCATIONS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS fmd.sample_budget_allocations();
ALTER TABLE fmd.sample_budget_allocations
ADD COLUMN IF NOT EXISTS id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS fund_source_id INTEGER,
ADD COLUMN IF NOT EXISTS fiscal_year INTEGER,
ADD COLUMN IF NOT EXISTS amount NUMERIC(18,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'Draft',
ADD COLUMN IF NOT EXISTS remarks TEXT,
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS created_by INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT NOW(),
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP;
-- sample_budget_allocations indexes
CREATE INDEX IF NOT EXISTS idx_sample_budget_allocations_fund_source_id ON fmd.sample_budget_allocations(fund_source_id);
CREATE INDEX IF NOT EXISTS idx_sample_budget_allocations_fiscal_year ON fmd.sample_budget_allocations(fiscal_year);
CREATE INDEX IF NOT EXISTS idx_sample_budget_allocations_composite ON fmd.sample_budget_allocations(fund_source_id, fiscal_year);
-- Notes:
--  * Money is NUMERIC, never FLOAT.
--  * Add a new column later = add one more `ADD COLUMN IF NOT EXISTS` line above.


-- ============================================================================
-- SECTION 2 — FOREIGN KEY / CONSTRAINT (after all tables exist)
-- Location : _assets/sql/04 - schema_alter_table_structure/01 - FMD/04 - alter_table/
-- File     : 12 - sample_budget_allocations_add_fk.sql
-- Postgres has no ADD CONSTRAINT IF NOT EXISTS, so guard it with a DO block.
-- ============================================================================

-- =============================================
-- SAMPLE_BUDGET_ALLOCATIONS CONSTRAINTS
-- =============================================
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_sample_budget_allocations_fund_source'
    ) THEN
        ALTER TABLE fmd.sample_budget_allocations
        ADD CONSTRAINT fk_sample_budget_allocations_fund_source
        FOREIGN KEY (fund_source_id) REFERENCES shared.fund_sources(id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'uq_sample_budget_allocations_source_year'
    ) THEN
        ALTER TABLE fmd.sample_budget_allocations
        ADD CONSTRAINT uq_sample_budget_allocations_source_year
        UNIQUE (fund_source_id, fiscal_year);
    END IF;
END $$;


-- ============================================================================
-- SECTION 3 — VIEW
-- Location : _assets/sql/02 - schema_view_structure/02 - FMD/02 - views/
-- File     : 05 - v_sample_budget_allocations.sql
-- To change an EXISTING view later: new file in
-- `04 - schema_alter_table_structure/[module]/05 - alter_view/`.
-- ============================================================================

-- =============================================
-- V_SAMPLE_BUDGET_ALLOCATIONS VIEW
-- Purpose : Allocations joined with fund source name for list pages and reports.
-- Grain   : One row per allocation (fund_source_id, fiscal_year).
-- =============================================
DROP VIEW IF EXISTS fmd.v_sample_budget_allocations CASCADE;
CREATE VIEW fmd.v_sample_budget_allocations AS
SELECT
    a.id,
    a.fiscal_year,
    a.amount,
    a.status,
    fs.name AS fund_source_name
FROM fmd.sample_budget_allocations a
LEFT JOIN shared.fund_sources fs ON fs.id = a.fund_source_id
WHERE a.is_active = TRUE;


-- ============================================================================
-- SECTION 4 — FUNCTION
-- Location : _assets/sql/03 - schema_function_structure/02 - FMD/03 - function/
-- File     : 03 - fn_sample_total_allocation.sql
-- ============================================================================

-- =============================================
-- FN_SAMPLE_TOTAL_ALLOCATION FUNCTION
-- Purpose : Total approved allocation for a fiscal year (0 when no data).
-- =============================================
CREATE OR REPLACE FUNCTION fmd.fn_sample_total_allocation(p_fiscal_year INTEGER)
RETURNS NUMERIC
LANGUAGE sql
STABLE
AS $$
    SELECT COALESCE(SUM(amount), 0)
    FROM fmd.sample_budget_allocations
    WHERE fiscal_year = p_fiscal_year
      AND status = 'Approved'
      AND is_active = TRUE;
$$;


-- ============================================================================
-- SECTION 5 — SEED / SAMPLE DATA
-- Location : _assets/sql/05 - schema_sample_data_structure/02 - FMD/05 -sample_data/
--            (folder is literally `05 -sample_data`, no space after the dash)
-- File     : 04 - sample_budget_allocations_data.sql
-- ============================================================================

-- =============================================
-- SAMPLE_BUDGET_ALLOCATIONS DATA
-- =============================================
INSERT INTO fmd.sample_budget_allocations (fund_source_id, fiscal_year, amount, status, remarks)
VALUES
    (1, 2025, 1500000.00, 'Approved', 'Sample data'),
    (2, 2025,  750000.50, 'Draft',    'Sample data')
ON CONFLICT (fund_source_id, fiscal_year) DO NOTHING;


-- ============================================================================
-- SECTION 6 — DATA FIX / MAINTENANCE
-- Location : _assets/sql/07 - schema_maintenance_structure/[module]/07 - maintenance/
-- Always scope with WHERE and make it safe to re-run. Claude does NOT run
-- UPDATE/DELETE against a shared or production DB; the dev reviews and runs it.
-- ============================================================================

-- =============================================
-- SAMPLE_BUDGET_ALLOCATIONS STATUS NORMALIZATION
-- =============================================
UPDATE fmd.sample_budget_allocations
SET status = 'For Approval'
WHERE status IN ('Pending', 'For approval');


-- ============================================================================
-- SECTION 7 — DEV-ONLY TRUNCATE (keep commented out)
-- Location : _assets/sql/06 - schema_truncate_structure/[module]/06 - truncate/
-- ============================================================================

-- =============================================
-- SAMPLE_BUDGET_ALLOCATIONS TRUNCATE
-- =============================================
-- TRUNCATE TABLE fmd.sample_budget_allocations RESTART IDENTITY CASCADE;
