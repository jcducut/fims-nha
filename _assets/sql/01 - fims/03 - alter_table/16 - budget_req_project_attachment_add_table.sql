-- ============================================================
-- MIGRATION: add fims.budget_req_project_attachment
--
-- New table for per-project supporting documents on a budget request
-- (uploaded to object storage or pasted as an external link). See
-- _assets/sql/01 - fims/01 - table/16 - fims_budget_req_project_attachment.sql
-- for the full rationale behind keying off (budget_req_program_id,
-- project_code) instead of budget_req_project_id.
--
-- Idempotent: CREATE TABLE/INDEX IF NOT EXISTS, safe to rerun.
-- ============================================================

CREATE TABLE IF NOT EXISTS fims.budget_req_project_attachment (
    attachment_id SERIAL PRIMARY KEY,
    budget_reference_number VARCHAR(20) NOT NULL REFERENCES fims.budget_req(budget_reference_number) ON DELETE CASCADE,
    budget_req_program_id INTEGER NOT NULL REFERENCES fims.budget_req_program(budget_req_program_id) ON DELETE CASCADE,
    project_code VARCHAR(30) NOT NULL REFERENCES fims.project(project_code),
    document_name VARCHAR(255) NOT NULL,
    storage_key VARCHAR(500),
    external_link VARCHAR(1000),
    uploaded_by INTEGER,
    uploaded_at TIMESTAMP NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_budget_req_project_attachment_source
        CHECK (storage_key IS NOT NULL OR external_link IS NOT NULL)
);

CREATE INDEX IF NOT EXISTS idx_budget_req_project_attachment_scope
    ON fims.budget_req_project_attachment (budget_req_program_id, project_code);
