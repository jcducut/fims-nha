-- Header row for a budget request, for a given fiscal year — shape depends on
-- the expense class:
--   CO (Capital Outlay)                 -> program_code XOR project_code, amount, location — single row, no line items
--   PS (Personnel Services)             -> program_code/project_code/amount/location left NULL;
--   MOOE (Maintenance & Other Op. Exp.)    the actual allocations live in fims.budget_line_items,
--                                          one row per chart-of-accounts entry, FK'd to this header
CREATE TABLE IF NOT EXISTS fims.budget_req (
    budget_reference_number SERIAL PRIMARY KEY,
    fiscal_yr SMALLINT NOT NULL REFERENCES fims.fiscal_year(fiscal_yr),
    expense_class_id INTEGER NOT NULL REFERENCES fims.expense_class(expense_class_id),
    program_code VARCHAR(20) REFERENCES fims.program(program_code),
    project_code VARCHAR(20) REFERENCES fims.project(project_code),
    amount NUMERIC(15, 2),
    location VARCHAR(255),
    status_id INTEGER REFERENCES fims.settings_status(id),
    CONSTRAINT chk_budget_req_program_project_exclusive
        CHECK (NOT (program_code IS NOT NULL AND project_code IS NOT NULL))
);
