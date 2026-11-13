-- ============================================================
-- ALTER: fims.budget_line_items
-- Adds budget_req_project_id (nullable FK to fims.budget_req_project,
-- ON DELETE CASCADE) — a line item now belongs to a specific Project
-- "line item" within its budget_req header, not the header directly.
-- budget_reference_number is kept alongside it (small, deliberate
-- denormalization) so existing header-level joins/aggregates keep
-- working unchanged; the service layer keeps both in sync on write.
--
-- Also renames requested_amount -> proposed_amount to match the
-- "Proposed Amount" field naming used throughout the redesigned form.
-- ============================================================

ALTER TABLE fims.budget_line_items
    ADD COLUMN IF NOT EXISTS budget_req_project_id INTEGER REFERENCES fims.budget_req_project(budget_req_project_id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_fims_budget_line_items_budget_req_project_id ON fims.budget_line_items (budget_req_project_id);

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'budget_line_items' AND column_name = 'requested_amount'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'budget_line_items' AND column_name = 'proposed_amount'
    ) THEN
        ALTER TABLE fims.budget_line_items RENAME COLUMN requested_amount TO proposed_amount;
    END IF;
END $$;

COMMENT ON COLUMN fims.budget_line_items.proposed_amount IS
    'Proposed Amount for this chart-of-accounts allocation (renamed from requested_amount).';
