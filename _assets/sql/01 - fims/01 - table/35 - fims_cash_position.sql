-- ============================================================================
-- Cash Position — ported from TREASURY's treasury.cash_position
-- (src/Entity/CashPosition.php), backing Check Preparation's cash
-- availability display and soft insufficient-cash check
-- (CheckProcessingService::getCashAvailability()/saveCheck()).
--
-- One row per bank account per day. available_balance is a generated
-- column (closing_balance - restricted_amount), same as TREASURY — the
-- app only ever writes opening_balance/total_receipts/total_disbursements/
-- closing_balance/restricted_amount directly, never available_balance.
-- fund_source_id is dropped (this project has no shared.fund_sources
-- table — fims.bank_accounts.fund_source is already plain text, not an
-- FK'd id) since it isn't read by any of the ported queries anyway.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.cash_position();
ALTER TABLE fims.cash_position
ADD COLUMN IF NOT EXISTS cash_position_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS bank_account_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS position_date DATE NOT NULL,
ADD COLUMN IF NOT EXISTS opening_balance NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS total_receipts NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS total_disbursements NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS closing_balance NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS restricted_amount NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS bank_statement_balance NUMERIC(18,2),
ADD COLUMN IF NOT EXISTS reconciled BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS created_by INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Postgres can't ADD COLUMN ... GENERATED on an existing table in one
-- idempotent step the same way as the plain columns above, so it's added
-- separately, guarded by an existence check.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'cash_position' AND column_name = 'available_balance'
    ) THEN
        ALTER TABLE fims.cash_position
        ADD COLUMN available_balance NUMERIC(18,2) GENERATED ALWAYS AS (closing_balance - restricted_amount) STORED;
    END IF;
END $$;

COMMENT ON TABLE fims.cash_position IS 'Daily cash position per bank account — backs Check Preparation''s cash availability display/soft check';

CREATE UNIQUE INDEX IF NOT EXISTS uq_cash_position_bank_date ON fims.cash_position (bank_account_id, position_date);
CREATE INDEX IF NOT EXISTS idx_cash_position_bank_account ON fims.cash_position (bank_account_id);
