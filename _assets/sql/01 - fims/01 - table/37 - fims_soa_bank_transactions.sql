-- ============================================================================
-- SOA (bank Statement of Account) upload + the bank transaction ledger it
-- feeds — ported from TREASURY's CashPosUpServices.php (treasury.
-- soa_transactions / treasury.bank_transactions), backing Position
-- Updates' "Upload SOA" flow, the SOA vs Available Balance comparison,
-- and the SOA Transactions grouped-by-upload accordion.
--
-- fims.bank_transactions.transaction_id is a deterministic string
-- (SOA-YYYYMMDD-HHiiss-XXXXXXXX, an MD5-based natural key computed from
-- the transaction's own date/time/type/direction/amount/description/
-- check_no — see CashPositionSoaService::bankTxnId()) — UNIQUE on its own
-- is enough to dedupe re-uploads of the same statement line, so this
-- project doesn't need TREASURY's separately-named
-- "bank_transactions_natural_key" constraint (whose exact column list
-- wasn't recoverable from the reference source).
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.soa_transactions();
ALTER TABLE fims.soa_transactions
ADD COLUMN IF NOT EXISTS soa_transaction_id BIGSERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS bank_account_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS transaction_date DATE NOT NULL,
ADD COLUMN IF NOT EXISTS transaction_time TIME,
ADD COLUMN IF NOT EXISTS description TEXT,
ADD COLUMN IF NOT EXISTS debit_amount NUMERIC(18,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS credit_amount NUMERIC(18,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS balance NUMERIC(18,2),
ADD COLUMN IF NOT EXISTS branch VARCHAR(100),
ADD COLUMN IF NOT EXISTS check_no VARCHAR(50),
ADD COLUMN IF NOT EXISTS other_details TEXT,
ADD COLUMN IF NOT EXISTS uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS uploaded_by INTEGER,
ADD COLUMN IF NOT EXISTS upload_batch_id UUID,
ADD COLUMN IF NOT EXISTS upload_type VARCHAR(10) DEFAULT 'MONTHLY',
ADD COLUMN IF NOT EXISTS period_start DATE,
ADD COLUMN IF NOT EXISTS period_end DATE;

COMMENT ON TABLE fims.soa_transactions IS 'Parsed bank-statement (SOA) lines, one row per uploaded statement line, grouped by upload_batch_id';

CREATE INDEX IF NOT EXISTS idx_soa_transactions_bank_account ON fims.soa_transactions (bank_account_id);
CREATE INDEX IF NOT EXISTS idx_soa_transactions_date ON fims.soa_transactions (transaction_date);
CREATE INDEX IF NOT EXISTS idx_soa_transactions_batch ON fims.soa_transactions (upload_batch_id);

CREATE TABLE IF NOT EXISTS fims.bank_transactions();
ALTER TABLE fims.bank_transactions
ADD COLUMN IF NOT EXISTS bank_transaction_id BIGSERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS transaction_type VARCHAR(20) NOT NULL, -- CHECK | COLLECTION | FTLA | INTEREST
ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(60) NOT NULL, -- deterministic natural key, e.g. SOA-YYYYMMDD-HHiiss-XXXXXXXX
ADD COLUMN IF NOT EXISTS reference_number VARCHAR(50),
ADD COLUMN IF NOT EXISTS transaction_date DATE NOT NULL,
ADD COLUMN IF NOT EXISTS bank_account_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS amount NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS description TEXT,
ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'VALIDATED',
ADD COLUMN IF NOT EXISTS counterpart VARCHAR(150),
ADD COLUMN IF NOT EXISTS direction VARCHAR(10) NOT NULL, -- INFLOW | OUTFLOW
ADD COLUMN IF NOT EXISTS source_created_at TIMESTAMP,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.bank_transactions IS 'Bank-account transaction ledger posted from SOA uploads — backs Cash Position''s receipts/disbursements sync and the Available Cash Balances outstanding-checks view';

CREATE UNIQUE INDEX IF NOT EXISTS uq_bank_transactions_transaction_id ON fims.bank_transactions (transaction_id);
CREATE INDEX IF NOT EXISTS idx_bank_transactions_bank_account ON fims.bank_transactions (bank_account_id);
CREATE INDEX IF NOT EXISTS idx_bank_transactions_date ON fims.bank_transactions (transaction_date);
