-- ============================================================================
-- Budget allocations — one row per allocation (memo / PR / PO tracking) made
-- against a fiscal year for a requesting department/district.
--
-- Soft-delete: rows are never removed, deleted_at is stamped instead.
-- Foreign keys live in "03 - alter_table/08 - budget_allocation_add_fk.sql".
-- program_id / sub_program_id / project_id are plain INTEGER columns (no FK):
-- fims.program, fims.sub_program and fims.project are keyed by *_code VARCHAR,
-- not by an integer id.
-- ============================================================================

-- =============================================
-- BUDGET_ALLOCATION TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS fims.budget_allocation();
ALTER TABLE fims.budget_allocation
ADD COLUMN IF NOT EXISTS allocation_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS memo_ref_no VARCHAR(50),
ADD COLUMN IF NOT EXISTS allocation_type VARCHAR(20),
ADD COLUMN IF NOT EXISTS allocation_number VARCHAR(100),
ADD COLUMN IF NOT EXISTS fiscal_year INTEGER,
ADD COLUMN IF NOT EXISTS requesting_department_id INTEGER,
ADD COLUMN IF NOT EXISTS requesting_district_id INTEGER,
ADD COLUMN IF NOT EXISTS program_id INTEGER,
ADD COLUMN IF NOT EXISTS sub_program_id INTEGER,
ADD COLUMN IF NOT EXISTS project_id INTEGER,
ADD COLUMN IF NOT EXISTS vendor_id INTEGER,
ADD COLUMN IF NOT EXISTS parent_id INTEGER,
ADD COLUMN IF NOT EXISTS document_type VARCHAR(20),
ADD COLUMN IF NOT EXISTS description TEXT,
ADD COLUMN IF NOT EXISTS pr_number VARCHAR(50),
ADD COLUMN IF NOT EXISTS pr_amount NUMERIC(18,2),
ADD COLUMN IF NOT EXISTS po_number VARCHAR(50),
ADD COLUMN IF NOT EXISTS po_amount NUMERIC(18,2),
ADD COLUMN IF NOT EXISTS workflow_route VARCHAR(20),
ADD COLUMN IF NOT EXISTS category_id INTEGER,
ADD COLUMN IF NOT EXISTS is_bidding BOOLEAN,
ADD COLUMN IF NOT EXISTS line_transaction JSONB,
ADD COLUMN IF NOT EXISTS multiple_node_id UUID,
ADD COLUMN IF NOT EXISTS status_id INTEGER,
ADD COLUMN IF NOT EXISTS user_id INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;

COMMENT ON TABLE  fims.budget_allocation IS 'Budget allocations (memo / PR / PO tracking) per fiscal year and requesting department/district';
COMMENT ON COLUMN fims.budget_allocation.parent_id IS 'Self-reference: the allocation this row derives from (fims.budget_allocation.allocation_id)';
COMMENT ON COLUMN fims.budget_allocation.deleted_at IS 'Soft delete: NULL = active, timestamp = deleted';

-- budget_allocation indexes
CREATE INDEX IF NOT EXISTS idx_budget_allocation_fiscal_year            ON fims.budget_allocation (fiscal_year);
CREATE INDEX IF NOT EXISTS idx_budget_allocation_requesting_department  ON fims.budget_allocation (requesting_department_id);
CREATE INDEX IF NOT EXISTS idx_budget_allocation_program_id             ON fims.budget_allocation (program_id);
CREATE INDEX IF NOT EXISTS idx_budget_allocation_project_id             ON fims.budget_allocation (project_id);
CREATE INDEX IF NOT EXISTS idx_budget_allocation_vendor_id              ON fims.budget_allocation (vendor_id);
CREATE INDEX IF NOT EXISTS idx_budget_allocation_parent_id              ON fims.budget_allocation (parent_id);
CREATE INDEX IF NOT EXISTS idx_budget_allocation_status_id              ON fims.budget_allocation (status_id);
CREATE INDEX IF NOT EXISTS idx_budget_allocation_memo_ref_no            ON fims.budget_allocation (memo_ref_no);
CREATE INDEX IF NOT EXISTS idx_budget_allocation_allocation_number      ON fims.budget_allocation (allocation_number);
CREATE INDEX IF NOT EXISTS idx_budget_allocation_multiple_node_id       ON fims.budget_allocation (multiple_node_id);
CREATE INDEX IF NOT EXISTS idx_budget_allocation_deleted_at             ON fims.budget_allocation (deleted_at);
