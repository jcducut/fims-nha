-- ============================================================
-- MIGRATION: fims.budget_req.budget_reference_number SERIAL -> "BP-NNN" string PK
--
-- Moves the budget request header's primary key from an auto-incrementing
-- integer to a string column. Existing integer values are cast to text
-- as-is (row 3 stays '3', not reformatted) -- same logical id, same
-- underlying value, just a different column type. Going forward, new rows
-- get their id from GlobalService::generateBudgetReferenceNumber() (a
-- MAX+1 scan, same pattern as generateProgramCode()/
-- ResponsibilityCenterService::generateRcId()) instead of the dropped
-- SERIAL sequence, so new rows will be formatted "BP-NNN" while old rows
-- stay as their plain former integer text.
--
-- Three tables carry this as a foreign key -- fims.budget_req_program,
-- fims.budget_req_project, fims.budget_line_items -- all converted with the
-- exact same transformation, so every existing cross-reference still points
-- at the same logical row under its new id. Each FK was declared inline/
-- unnamed at CREATE TABLE time, so this drops/re-adds them by introspection
-- rather than a guessed name.
--
-- Run as one transaction. Idempotent: every step (including the FK drop) is
-- guarded by checking the column's current data_type first, so re-running
-- after it's already VARCHAR is a true no-op.
-- ============================================================

BEGIN;

-- ── Drop every FK referencing fims.budget_req (the 3 above) ─────────────
-- Guarded the same way as the column conversions below: once the parent PK
-- is already VARCHAR, the migration has already run, so there's nothing to
-- re-convert and the FKs are left alone instead of being churned every rerun.
DO $$
DECLARE
    con RECORD;
BEGIN
    IF (SELECT data_type FROM information_schema.columns
         WHERE table_schema = 'fims' AND table_name = 'budget_req'
           AND column_name = 'budget_reference_number') <> 'character varying'
    THEN
        FOR con IN
            SELECT conrelid::regclass AS tbl, conname
            FROM pg_constraint
            WHERE contype = 'f'
              AND confrelid = 'fims.budget_req'::regclass
        LOOP
            EXECUTE format('ALTER TABLE %s DROP CONSTRAINT %I', con.tbl, con.conname);
        END LOOP;
    END IF;
END $$;

-- ── Convert the parent PK ───────────────────────────────────────────────
DO $$
BEGIN
    IF (SELECT data_type FROM information_schema.columns
         WHERE table_schema = 'fims' AND table_name = 'budget_req'
           AND column_name = 'budget_reference_number') <> 'character varying'
    THEN
        ALTER TABLE fims.budget_req
            ALTER COLUMN budget_reference_number DROP DEFAULT;

        ALTER TABLE fims.budget_req
            ALTER COLUMN budget_reference_number TYPE VARCHAR(20)
            USING budget_reference_number::text;

        DROP SEQUENCE IF EXISTS fims.budget_req_budget_reference_number_seq;
    END IF;
END $$;

-- ── Convert the 3 child FK columns the same way ──────────────────────────
DO $$
BEGIN
    IF (SELECT data_type FROM information_schema.columns
         WHERE table_schema = 'fims' AND table_name = 'budget_req_program'
           AND column_name = 'budget_reference_number') <> 'character varying'
    THEN
        ALTER TABLE fims.budget_req_program
            ALTER COLUMN budget_reference_number TYPE VARCHAR(20)
            USING budget_reference_number::text;
    END IF;
END $$;

DO $$
BEGIN
    IF (SELECT data_type FROM information_schema.columns
         WHERE table_schema = 'fims' AND table_name = 'budget_req_project'
           AND column_name = 'budget_reference_number') <> 'character varying'
    THEN
        ALTER TABLE fims.budget_req_project
            ALTER COLUMN budget_reference_number TYPE VARCHAR(20)
            USING budget_reference_number::text;
    END IF;
END $$;

DO $$
BEGIN
    IF (SELECT data_type FROM information_schema.columns
         WHERE table_schema = 'fims' AND table_name = 'budget_line_items'
           AND column_name = 'budget_reference_number') <> 'character varying'
    THEN
        ALTER TABLE fims.budget_line_items
            ALTER COLUMN budget_reference_number TYPE VARCHAR(20)
            USING budget_reference_number::text;
    END IF;
END $$;

-- ── Re-add the 3 FKs ──────────────────────────────────────────────────────
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'budget_req_program_budget_reference_number_fkey') THEN
        ALTER TABLE fims.budget_req_program
            ADD CONSTRAINT budget_req_program_budget_reference_number_fkey
            FOREIGN KEY (budget_reference_number) REFERENCES fims.budget_req (budget_reference_number) ON DELETE CASCADE;
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'budget_req_project_budget_reference_number_fkey') THEN
        ALTER TABLE fims.budget_req_project
            ADD CONSTRAINT budget_req_project_budget_reference_number_fkey
            FOREIGN KEY (budget_reference_number) REFERENCES fims.budget_req (budget_reference_number) ON DELETE CASCADE;
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'budget_line_items_budget_reference_number_fkey') THEN
        ALTER TABLE fims.budget_line_items
            ADD CONSTRAINT budget_line_items_budget_reference_number_fkey
            FOREIGN KEY (budget_reference_number) REFERENCES fims.budget_req (budget_reference_number) ON DELETE CASCADE;
    END IF;
END $$;

COMMIT;
