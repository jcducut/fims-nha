-- ============================================================
-- ALTER: fims.budget_line_items — drop program_source_code and program_uses
-- The "Program Sources" / "Program Uses" choices of the Budget Processing modal (added by
-- 17 - budget_line_items_add_program_source_code.sql and 18 - budget_line_items_add_program_uses.sql) are gone:
-- the modal, the Budget Preview modal, BudgetLineItemService::applyProcessing and BudgetReqService::endorse no
-- longer read or write them.
--
-- THIS DELETES THE DATA in those two columns. Nothing else depends on them. Each column's foreign key
-- (budget_line_items_program_source_code_fkey / budget_line_items_program_uses_fkey) and index
-- (idx_budget_line_items_program_source_code / idx_budget_line_items_program_uses) go with it — DROP COLUMN
-- removes them, so there are no separate DROP INDEX / DROP CONSTRAINT statements; no view, function
-- or trigger refers to them (if one ever does, the DROP stops with an error instead of silently taking that
-- object with it — there is deliberately no CASCADE).
-- Idempotent: safe to run any number of times (a database that never had the columns is left alone).
-- ============================================================
ALTER TABLE fims.budget_line_items
    DROP COLUMN IF EXISTS program_source_code,
    DROP COLUMN IF EXISTS program_uses;
