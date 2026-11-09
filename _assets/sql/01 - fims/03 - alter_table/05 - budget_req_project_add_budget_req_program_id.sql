-- ALTER: fims.budget_req_project
-- A project block now belongs to a specific Program within its header,
-- not the header directly. budget_reference_number is kept (same
-- denormalization precedent as budget_line_items.budget_reference_number)
-- so existing header-level count/sum aggregates keep working unchanged.
ALTER TABLE fims.budget_req_project
    ADD COLUMN IF NOT EXISTS budget_req_program_id INTEGER REFERENCES fims.budget_req_program(budget_req_program_id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_fims_budget_req_project_budget_req_program_id ON fims.budget_req_project (budget_req_program_id);
