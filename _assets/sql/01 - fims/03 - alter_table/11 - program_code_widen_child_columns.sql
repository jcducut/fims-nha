-- ============================================================
-- ALTER: widen the program_code / sub_program_code copies to VARCHAR(64)
-- fims.program.program_code and fims.sub_program.sub_program_code are
-- VARCHAR(64) primary keys, but the tables that copy a program or
-- sub-program code were declared VARCHAR(20). Generated program codes are
-- "<year>-<rc_code>-<NNN>" (GlobalService::generateProgramCode()), e.g.
-- "2026-35-074-0000000-032-003" — 27 characters — so saving anything
-- against such a program failed with
--   SQLSTATE[22001]: value too long for type character varying(20)
-- (first seen on POST /api/budget_req/add -> BudgetReqService::addProgram(),
-- the INSERT into fims.budget_req_program).
--
-- Every column below holds a value that is a foreign key / copy of
-- fims.program.program_code or fims.sub_program.sub_program_code, so it is
-- widened to the same VARCHAR(64) as its source:
--   fims.budget_req_program  program_code, sub_program_code
--   fims.project             program_code, sub_program_code
--   fims.budget_allocation   program_code, sub_program_code
--   fims.purchase_orders     program_code
-- (project_code stays VARCHAR(20): fims.project.project_code is itself
-- VARCHAR(20) and holds short "PRJ-N" codes.)
--
-- Widening a VARCHAR is a metadata-only change: no table rewrite, existing
-- data and foreign keys are untouched. Idempotent — a column that is
-- missing, or already 64+ characters wide, is skipped — so it is safe to
-- run any number of times.
-- ============================================================
DO $$
DECLARE
    col RECORD;
BEGIN
    FOR col IN
        SELECT c.table_name, c.column_name
        FROM information_schema.columns c
        WHERE c.table_schema = 'fims'
          AND (c.table_name, c.column_name) IN (
                ('budget_req_program', 'program_code'),
                ('budget_req_program', 'sub_program_code'),
                ('project',            'program_code'),
                ('project',            'sub_program_code'),
                ('budget_allocation',  'program_code'),
                ('budget_allocation',  'sub_program_code'),
                ('purchase_orders',    'program_code')
          )
          AND c.character_maximum_length IS NOT NULL
          AND c.character_maximum_length < 64
    LOOP
        EXECUTE format('ALTER TABLE fims.%I ALTER COLUMN %I TYPE VARCHAR(64)', col.table_name, col.column_name);
        RAISE NOTICE 'Widened fims.%.% to VARCHAR(64)', col.table_name, col.column_name;
    END LOOP;
END
$$;
