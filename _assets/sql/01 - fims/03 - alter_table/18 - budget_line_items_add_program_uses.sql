-- ============================================================
-- ALTER: fims.budget_line_items — add program_uses
-- The "Program Uses" choice made per line item in the Budget Processing modal (next to "Program Sources")
-- and saved when the request is Endorsed (BudgetLineItemService::applyProcessing). A program use IS a
-- sub-program: the value is a fims.sub_program.sub_program_code, so it is a VARCHAR(64) like that key
-- (see 10 - fims_sub_program.sql), not an integer id, and the picker lists the rows of fims.sub_program
-- (GET /sub_program/all). Nullable: it is optional, and existing rows have none.
-- Idempotent: safe to run any number of times.
-- ============================================================
ALTER TABLE fims.budget_line_items
    ADD COLUMN IF NOT EXISTS program_uses VARCHAR(64);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'budget_line_items_program_uses_fkey') THEN
        ALTER TABLE fims.budget_line_items
            ADD CONSTRAINT budget_line_items_program_uses_fkey
            FOREIGN KEY (program_uses) REFERENCES fims.sub_program (sub_program_code);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_budget_line_items_program_uses ON fims.budget_line_items (program_uses);

COMMENT ON COLUMN fims.budget_line_items.program_uses IS
    'Program use of this line item: a fims.sub_program.sub_program_code, chosen during Budget Processing. NULL = none chosen.';
