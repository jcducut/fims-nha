-- ============================================================================
-- fims.bank_accounts — extends the existing table (01 - table/26) with the
-- fields the Bank Account Management module needs (ported from TREASURY's
-- bankaccmanagement.php / BankAccManServices.php): account_type (CURRENT /
-- SAVINGS / NON_DRAWING_SAVINGS / TIME_DEPOSIT / TRUST), account_classification
-- (the "Purpose" field — COLLECTION / DISBURSEMENT / COE_MOOE /
-- CAPITAL_OUTLAY / EHAP / SPECIAL_PURPOSE / PROJECT / JOINT_VENTURE / TRUST),
-- bank_branch, fund_source (plain text — this project has no fund_sources
-- lookup table, same convention as disbursement_vouchers.fund_source),
-- department_id (FK uac.departments — "Region/Office", same pattern as
-- check_region_releases.department_id added in "22 - ...add_email_and_
-- department.sql"), is_main_depository, date_opened, last_transaction_date,
-- and a new `status` enum (ACTIVE/DORMANT/CLOSED) that is kept in sync with
-- the pre-existing `is_active` boolean so CheckProcessingService::
-- getBankAccounts()'s "WHERE is_active = TRUE" filter keeps working
-- unmodified for every other module that already depends on this table.
--
-- Also adds fims.bank_account_documents (Google-Drive-link attachments —
-- this project stores links, not uploaded files, matching the pattern
-- already named but unimplemented in CheckProcessingService::
-- saveRegionDriveLink()) and fims.bank_account_audit_log (before/after JSON
-- diff trail for create/update/status-change actions, same shape as
-- TREASURY's shared.bank_account_audit_log).
-- ============================================================================

ALTER TABLE fims.bank_accounts
ADD COLUMN IF NOT EXISTS account_type VARCHAR(30),
ADD COLUMN IF NOT EXISTS account_classification VARCHAR(30),
ADD COLUMN IF NOT EXISTS bank_branch VARCHAR(150),
ADD COLUMN IF NOT EXISTS fund_source VARCHAR(100),
ADD COLUMN IF NOT EXISTS department_id INTEGER,
ADD COLUMN IF NOT EXISTS is_main_depository BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS date_opened DATE,
ADD COLUMN IF NOT EXISTS last_transaction_date DATE,
ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE';

-- Backfill status from the existing is_active flag for rows that predate
-- this migration (the Check Disbursement Voucher module's seed accounts).
UPDATE fims.bank_accounts SET status = 'ACTIVE' WHERE is_active = TRUE AND status IS DISTINCT FROM 'ACTIVE' AND status NOT IN ('DORMANT', 'CLOSED');
UPDATE fims.bank_accounts SET status = 'CLOSED' WHERE is_active = FALSE AND status = 'ACTIVE';

CREATE INDEX IF NOT EXISTS idx_bank_accounts_status ON fims.bank_accounts (status);
CREATE INDEX IF NOT EXISTS idx_bank_accounts_department_id ON fims.bank_accounts (department_id);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_bank_accounts_department') THEN
        ALTER TABLE fims.bank_accounts
        ADD CONSTRAINT fk_bank_accounts_department
        FOREIGN KEY (department_id) REFERENCES uac.departments(department_id);
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS fims.bank_account_documents();
ALTER TABLE fims.bank_account_documents
ADD COLUMN IF NOT EXISTS document_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS bank_account_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS link_name VARCHAR(200) NOT NULL,
ADD COLUMN IF NOT EXISTS gdrive_link VARCHAR(500) NOT NULL,
ADD COLUMN IF NOT EXISTS added_by INTEGER,
ADD COLUMN IF NOT EXISTS added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

CREATE INDEX IF NOT EXISTS idx_bank_account_documents_account_id ON fims.bank_account_documents (bank_account_id);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_bank_account_documents_account') THEN
        ALTER TABLE fims.bank_account_documents
        ADD CONSTRAINT fk_bank_account_documents_account
        FOREIGN KEY (bank_account_id) REFERENCES fims.bank_accounts(bank_account_id);
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS fims.bank_account_audit_log();
ALTER TABLE fims.bank_account_audit_log
ADD COLUMN IF NOT EXISTS log_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS bank_account_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS action VARCHAR(30) NOT NULL,
ADD COLUMN IF NOT EXISTS before_data JSONB,
ADD COLUMN IF NOT EXISTS after_data JSONB,
ADD COLUMN IF NOT EXISTS performed_by INTEGER,
ADD COLUMN IF NOT EXISTS performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

CREATE INDEX IF NOT EXISTS idx_bank_account_audit_log_account_id ON fims.bank_account_audit_log (bank_account_id);

COMMENT ON TABLE fims.bank_account_documents IS 'Google-Drive-link attachments for a bank account (passbook photos, signature cards, etc.) — Bank Account Management module';
COMMENT ON TABLE fims.bank_account_audit_log IS 'Before/after JSON diff trail for Bank Account Management create/update/status-change actions';
