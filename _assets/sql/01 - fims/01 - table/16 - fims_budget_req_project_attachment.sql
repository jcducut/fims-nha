-- Supporting documents attached to one Project within a budget request,
-- uploaded to object storage (DO Spaces) or linked externally. Keyed off
-- (budget_req_program_id, project_code) rather than the ephemeral
-- budget_req_project_id -- BudgetReqService::updateProgram() deletes and
-- reinserts every project row under a program on each save, so an
-- attachment FK'd to that surrogate would be cascaded away on the very
-- next unrelated edit. budget_req_program_id (the program row itself) and
-- project_code (a real, stable entity) both survive that churn.
CREATE TABLE IF NOT EXISTS fims.budget_req_project_attachment (
    attachment_id SERIAL PRIMARY KEY,
    budget_reference_number VARCHAR(20) NOT NULL REFERENCES fims.budget_req(budget_reference_number) ON DELETE CASCADE,
    budget_req_program_id INTEGER NOT NULL REFERENCES fims.budget_req_program(budget_req_program_id) ON DELETE CASCADE,
    project_code VARCHAR(30) NOT NULL REFERENCES fims.project(project_code),
    document_name VARCHAR(255) NOT NULL,
    storage_key VARCHAR(500),      -- S3 object key, when uploaded
    external_link VARCHAR(1000),   -- pasted URL, when mode = link
    uploaded_by INTEGER,
    uploaded_at TIMESTAMP NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_budget_req_project_attachment_source
        CHECK (storage_key IS NOT NULL OR external_link IS NOT NULL)
);

CREATE INDEX IF NOT EXISTS idx_budget_req_project_attachment_scope
    ON fims.budget_req_project_attachment (budget_req_program_id, project_code);
