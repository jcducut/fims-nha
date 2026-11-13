-- ============================================================================
-- fims.cash_position — adds the fields needed by the Cash Position Updates
-- tab's manual-entry form (ported from TREASURY's CashPosUpServices.php
-- saveManualEntry()/updateCashPosition()): itemized restricted-fund
-- breakdown, adjustment entry, a free-text reference number, and remarks.
--
-- Unlike TREASURY (which stores fund_source_id directly on cash_position,
-- since one bank account can apparently post under different fund sources
-- over time), this project keeps fund_source as a single plain-text field
-- on fims.bank_accounts itself (see Bank Account Management) — so cash
-- position rows are simply joined to their account's fund_source rather
-- than carrying their own.
-- ============================================================================

ALTER TABLE fims.cash_position
ADD COLUMN IF NOT EXISTS restricted_breakdown JSONB,
ADD COLUMN IF NOT EXISTS adjustment_amount NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS adjustment_direction VARCHAR(10), -- ADDITION | DEDUCTION
ADD COLUMN IF NOT EXISTS adjustment_details TEXT,
ADD COLUMN IF NOT EXISTS reference_number VARCHAR(50),
ADD COLUMN IF NOT EXISTS remarks TEXT;

COMMENT ON COLUMN fims.cash_position.restricted_breakdown IS 'Itemized restriction entries [{label, amount}, ...] — restricted_amount is their sum when present, same convention as TREASURY''s Manual Entry modal';
