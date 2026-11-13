-- ============================================================================
-- Bank Accounts — backs the "Bank Account" dropdowns across every check-
-- processing tab (Check Preparation, Check Registry, Cancellation) and the
-- "Office Type" filter on Check Registry. One row per disbursing bank
-- account; office_type/department_type classify where it's drawn from
-- (matches the "Main Office" / "Region" option set used by Check Registry's
-- filter and the "Register Check Batch" modal's Department Type select).
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.bank_accounts();
ALTER TABLE fims.bank_accounts
ADD COLUMN IF NOT EXISTS bank_account_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS account_name VARCHAR(150) NOT NULL,
ADD COLUMN IF NOT EXISTS account_number VARCHAR(50),
ADD COLUMN IF NOT EXISTS bank_name VARCHAR(150),
ADD COLUMN IF NOT EXISTS office_type VARCHAR(20) DEFAULT 'main', -- main | region — matches Check Registry's "Office Type" filter
ADD COLUMN IF NOT EXISTS department_type VARCHAR(20), -- main | region | branch | project | other — Register Check Batch modal's Department Type
ADD COLUMN IF NOT EXISTS current_balance NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS created_by INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.bank_accounts IS 'Disbursing bank accounts — source of truth for the Bank Account dropdowns across the Check Disbursement Voucher module';

CREATE INDEX IF NOT EXISTS idx_bank_accounts_office_type ON fims.bank_accounts (office_type);
CREATE INDEX IF NOT EXISTS idx_bank_accounts_is_active   ON fims.bank_accounts (is_active);
