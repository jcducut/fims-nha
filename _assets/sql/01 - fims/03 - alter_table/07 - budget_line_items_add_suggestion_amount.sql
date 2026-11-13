-- ============================================================
-- ALTER: fims.budget_line_items
-- Adds suggestion_amount NUMERIC(18,2) — the suggested amount for the line
-- item. NOT NULL DEFAULT 0, like the other amount columns added in
-- "06 - budget_line_items_add_adjustment_obligation_disbursement_payable_component.sql",
-- so existing rows backfill to 0 and SUM()/arithmetic never meets a NULL.
-- ============================================================

ALTER TABLE fims.budget_line_items
    ADD COLUMN IF NOT EXISTS suggestion_amount NUMERIC(18, 2) NOT NULL DEFAULT 0;

COMMENT ON COLUMN fims.budget_line_items.suggestion_amount IS 'Suggested amount for this line item.';
