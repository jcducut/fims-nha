-- ============================================================
-- ALTER: fims.budget_line_items — add program_source_code
-- The "Program Sources" choice made per line item in the Budget Processing modal and saved when the
-- request is Endorsed (BudgetLineItemService::applyProcessing). A program source IS a program: the
-- value is a fims.program.program_code, so it is a VARCHAR(64) like every other program_code column
-- (see 11 - program_code_widen_child_columns.sql), not an integer id, and the dropdown lists the
-- rows of fims.program (GET /program/all). Nullable: it is optional, and existing rows have none.
-- Idempotent: safe to run any number of times.
-- ============================================================
ALTER TABLE fims.budget_line_items
    ADD COLUMN IF NOT EXISTS program_source_code VARCHAR(64);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'budget_line_items_program_source_code_fkey') THEN
        ALTER TABLE fims.budget_line_items
            ADD CONSTRAINT budget_line_items_program_source_code_fkey
            FOREIGN KEY (program_source_code) REFERENCES fims.program (program_code);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_budget_line_items_program_source_code ON fims.budget_line_items (program_source_code);

COMMENT ON COLUMN fims.budget_line_items.program_source_code IS
    'Program source of this line item: a fims.program.program_code, chosen during Budget Processing. NULL = none chosen.';
