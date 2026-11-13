-- ============================================================
-- ALTER: fims.budget_allocation
-- Program / Sub-Program / Project moved from integer ids (program_id,
-- sub_program_id, project_id) to codes (program_code, sub_program_code,
-- project_code) — fims.program / sub_program / project have no integer id.
-- The code columns, indexes and FKs are created by
-- "01 - table/15 - fims_budget_allocation.sql" and
-- "03 - alter_table/08 - budget_allocation_add_fk.sql" (which run before this
-- file); this one only removes the obsolete integer columns on databases that
-- were created before the change. A fresh install has none, so it is a no-op.
--
-- Safety: nothing here can map an integer id to a code, so if any row still has
-- one of the old columns filled in, the file raises instead of dropping data.
-- Dropping a column also drops its index (idx_budget_allocation_program_id,
-- idx_budget_allocation_project_id).
-- ============================================================

DO $$
DECLARE
    col text;
    in_use bigint;
BEGIN
    FOREACH col IN ARRAY ARRAY['program_id', 'sub_program_id', 'project_id'] LOOP
        IF EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = 'fims' AND table_name = 'budget_allocation' AND column_name = col
        ) THEN
            EXECUTE format('SELECT COUNT(*) FROM fims.budget_allocation WHERE %I IS NOT NULL', col) INTO in_use;
            IF in_use > 0 THEN
                RAISE EXCEPTION 'fims.budget_allocation.% still holds % non-NULL value(s); map them to program/sub-program/project codes before dropping the column.', col, in_use;
            END IF;
            EXECUTE format('ALTER TABLE fims.budget_allocation DROP COLUMN %I', col);
        END IF;
    END LOOP;
END $$;
