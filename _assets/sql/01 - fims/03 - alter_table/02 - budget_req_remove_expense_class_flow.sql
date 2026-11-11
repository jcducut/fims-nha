-- ============================================================
-- ALTER: fims.budget_req
-- Removes the old Expense-Class-driven CO/PS/MOOE branching:
--   - expense_class_id, project_code, amount, location columns dropped
--   - chk_budget_req_program_project_exclusive XOR check dropped
--   - sub_program_code added (nullable), completing the new
--     Fiscal Year / Program / Sub-Program header shape
--
-- Budget requests no longer carry a single program/project/amount on the
-- header — allocations now live in fims.budget_req_project (one row per
-- selected project) and fims.budget_line_items (one row per
-- chart-of-accounts allocation under a project).
--
-- program_code stays NULLABLE at the DB level intentionally — "required"
-- is a business rule enforced in BudgetReqService, not a NOT NULL
-- constraint, to avoid failing this migration against any existing
-- non-null-violating dev data.
-- ============================================================

ALTER TABLE fims.budget_req
    DROP CONSTRAINT IF EXISTS chk_budget_req_program_project_exclusive;

ALTER TABLE fims.budget_req
    DROP COLUMN IF EXISTS expense_class_id,
    DROP COLUMN IF EXISTS project_code,
    DROP COLUMN IF EXISTS amount,
    DROP COLUMN IF EXISTS location;

ALTER TABLE fims.budget_req
    ADD COLUMN IF NOT EXISTS sub_program_code VARCHAR(64) REFERENCES fims.sub_program(sub_program_code);

COMMENT ON COLUMN fims.budget_req.sub_program_code IS
    'Optional sub-program under program_code. Must belong to program_code when set (enforced in BudgetReqService).';
