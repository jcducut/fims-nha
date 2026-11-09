-- ============================================================
-- ALTER: fims.budget_line_items
-- Adds the execution-tracking amounts for a line item:
--   adjustment       - net adjustments applied to the proposed amount
--   obligation       - amount obligated against the line item
--   disbursement     - amount disbursed against the line item
--   account_payable  - amount still payable (obligated, not yet disbursed)
-- plus component_id and type_of_fund_id (both INTEGER / int4) to tag the
-- line item with a component and a type of fund.
--
-- The four amounts are NOT NULL DEFAULT 0 so existing rows backfill to 0
-- and SUM()/arithmetic over them never has to deal with NULLs.
-- component_id and type_of_fund_id are nullable (existing rows have
-- neither) and have no FK yet - there are no component / type-of-fund
-- tables in the schema.
-- ============================================================

ALTER TABLE fims.budget_line_items
    ADD COLUMN IF NOT EXISTS adjustment      NUMERIC(18, 2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS obligation      NUMERIC(18, 2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS disbursement    NUMERIC(18, 2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS account_payable NUMERIC(18, 2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS component_id    INTEGER,
    ADD COLUMN IF NOT EXISTS type_of_fund_id INTEGER;

COMMENT ON COLUMN fims.budget_line_items.adjustment      IS 'Net adjustments applied to the proposed amount.';
COMMENT ON COLUMN fims.budget_line_items.obligation      IS 'Amount obligated against this line item.';
COMMENT ON COLUMN fims.budget_line_items.disbursement    IS 'Amount disbursed against this line item.';
COMMENT ON COLUMN fims.budget_line_items.account_payable IS 'Amount payable (obligated but not yet disbursed).';
COMMENT ON COLUMN fims.budget_line_items.component_id    IS 'Component this line item belongs to (no FK yet).';
COMMENT ON COLUMN fims.budget_line_items.type_of_fund_id IS 'Type of fund for this line item (no FK yet).';
