-- One row = one Project "line item" added to a budget request header.
-- Chart-of-accounts line items (fims.budget_line_items) attach to this row
-- via budget_req_project_id, not directly to a project_code — Project and
-- Chart-of-Accounts are two distinct, parallel line-item concepts under one
-- budget_req header.
CREATE TABLE IF NOT EXISTS fims.budget_req_project (
    budget_req_project_id SERIAL PRIMARY KEY,
    budget_reference_number INTEGER NOT NULL REFERENCES fims.budget_req(budget_reference_number) ON DELETE CASCADE,
    project_code VARCHAR(20) NOT NULL REFERENCES fims.project(project_code)
);

CREATE INDEX IF NOT EXISTS idx_fims_budget_req_project_budget_reference_number ON fims.budget_req_project (budget_reference_number);
