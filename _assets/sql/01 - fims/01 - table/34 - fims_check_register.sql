-- ============================================================================
-- Check Register — ported faithfully from the TREASURY reference project's
-- treasury.check_register (_assets/TREASURY/app/services/CheckRegistryService.php).
-- Backs the Check Disbursement Voucher module's "Check Registry" tab.
--
-- This is a STANDALONE ledger of every physical blank check form received,
-- deliberately decoupled from fims.checks (which tracks a check actually
-- prepared/issued against a disbursement voucher — Check Preparation's own
-- concern). TREASURY keeps these as two separate tables for the same
-- reason: a check number's accountability lifecycle (received -> issued ->
-- encashed/cancelled/voided/spoiled/stale) is tracked here by UPDATE-in-
-- place on ONE row per check number, materialized in full at registration
-- time (not computed on the fly) — see addRegisterBatch() /
-- updateRegisterStatus() in CheckProcessingService.php. check_id is kept
-- as a loose (non-FK) pointer to fims.checks for whenever this check
-- number is actually prepared against a DV, same as TREASURY's own
-- check_register.check_id (also unenforced there).
--
-- booklet_no/check_series/department_type are plain string columns here
-- (not separate lookup tables), matching TREASURY exactly — the UI groups
-- rows by these client-side rather than the schema normalizing them.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.check_register();
ALTER TABLE fims.check_register
ADD COLUMN IF NOT EXISTS register_entry_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS bank_account_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS check_number VARCHAR(50) NOT NULL,
ADD COLUMN IF NOT EXISTS check_series VARCHAR(50),
ADD COLUMN IF NOT EXISTS check_id INTEGER,
ADD COLUMN IF NOT EXISTS entry_date DATE,
ADD COLUMN IF NOT EXISTS entry_type VARCHAR(20) NOT NULL DEFAULT 'RECEIVED', -- RECEIVED|ISSUED|CANCELLED|VOIDED|SPOILED|STALE|REPLACED
ADD COLUMN IF NOT EXISTS current_status VARCHAR(20) NOT NULL DEFAULT 'UNUSED', -- UNUSED|ISSUED|ENCASHED|OUTSTANDING|CANCELLED|VOIDED|SPOILED|STALE
ADD COLUMN IF NOT EXISTS payee_name VARCHAR(255),
ADD COLUMN IF NOT EXISTS amount NUMERIC(18,2),
ADD COLUMN IF NOT EXISTS voucher_reference VARCHAR(50),
ADD COLUMN IF NOT EXISTS booklet_no VARCHAR(50),
ADD COLUMN IF NOT EXISTS department_type VARCHAR(20), -- MAIN_OFFICE|REGION|BRANCH|PROJECT|OTHER
ADD COLUMN IF NOT EXISTS cancellation_reason TEXT,
ADD COLUMN IF NOT EXISTS replacement_check_number VARCHAR(50),
ADD COLUMN IF NOT EXISTS recorded_by INTEGER,
ADD COLUMN IF NOT EXISTS recorded_at TIMESTAMP,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.check_register IS 'Received blank check stock ledger — one row per physical check number, independent of fims.checks — backs the Check Registry tab';

-- Same-check-number-under-a-different-booklet is allowed to coexist (same
-- convention as TREASURY's Phase 2 dedup check in addBatch()), so the
-- uniqueness is on the (bank_account_id, check_number, booklet_no) triple,
-- not just (bank_account_id, check_number). COALESCE folds NULL booklet_no
-- into '' so two NULL-booklet rows for the same check number still collide.
CREATE UNIQUE INDEX IF NOT EXISTS uq_check_register_bank_number_booklet
    ON fims.check_register (bank_account_id, check_number, COALESCE(booklet_no, ''));

CREATE INDEX IF NOT EXISTS idx_check_register_bank_account ON fims.check_register (bank_account_id);
CREATE INDEX IF NOT EXISTS idx_check_register_status       ON fims.check_register (current_status);
CREATE INDEX IF NOT EXISTS idx_check_register_entry_type    ON fims.check_register (entry_type);
CREATE INDEX IF NOT EXISTS idx_check_register_series        ON fims.check_register (check_series);
CREATE INDEX IF NOT EXISTS idx_check_register_booklet_no    ON fims.check_register (booklet_no);
