-- ============================================================================
-- Checks — the central table of the Check Disbursement Voucher module: one
-- row per issued check, backing Tab 1 (Check Preparation) and read by
-- Registry/Claimants/Regions/Cancellation as the thing those tabs track the
-- lifecycle of.
--
-- status progression (see Check Preparation's status filter tabs and
-- Registry's status filter, which share this same value set):
--   prepared -> released -> encashed
--                        \-> outstanding -> stale
--   prepared|released -> cancelled (see fims.check_cancellations)
--   prepared -> voided | spoiled (never left the booklet)
-- entry_type distinguishes a registry entry representing stock being
-- "Received" into the booklet vs a check actually "Issued" against a DV —
-- matches Check Registry's "Entry Type" column.
--
-- dv_id is the real link to fims.disbursement_vouchers (FK added in
-- "03 - alter_table/21 - check_disbursement_tables_add_fk.sql"); dv_number
-- stays alongside it as a snapshot of disbursement_vouchers.dv_no at
-- preparation time, same convention as
-- disbursement_vouchers.rfa_allocation_number snapshotting fims.
-- budget_allocation — so this row still reads correctly even if the DV is
-- later edited. A check can only be prepared against a DV whose
-- status is FOR_TREASURY (see CheckProcessingService::saveCheck()), which
-- flips the DV to RELEASED via DisbursementVoucherService::changeStatus().
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.checks();
ALTER TABLE fims.checks
ADD COLUMN IF NOT EXISTS check_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS booklet_id INTEGER,
ADD COLUMN IF NOT EXISTS bank_account_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS check_number VARCHAR(50) NOT NULL,
ADD COLUMN IF NOT EXISTS check_date DATE,
ADD COLUMN IF NOT EXISTS dv_id INTEGER,
ADD COLUMN IF NOT EXISTS dv_number VARCHAR(50),
ADD COLUMN IF NOT EXISTS payee VARCHAR(255),
ADD COLUMN IF NOT EXISTS amount NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS entry_type VARCHAR(20) DEFAULT 'Issued', -- Received | Issued — Check Registry's "Entry Type" column
ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'prepared', -- prepared|released|encashed|outstanding|stale|cancelled|voided|spoiled
ADD COLUMN IF NOT EXISTS signatory1_name VARCHAR(100),
ADD COLUMN IF NOT EXISTS signatory1_designation VARCHAR(100),
ADD COLUMN IF NOT EXISTS signatory2_name VARCHAR(100),
ADD COLUMN IF NOT EXISTS signatory2_designation VARCHAR(100),
ADD COLUMN IF NOT EXISTS prepared_by INTEGER,
ADD COLUMN IF NOT EXISTS prepared_at TIMESTAMP,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.checks IS 'One row per prepared/issued check — the record every other check-processing tab (Registry, Claimants, Regions, Cancellation) tracks the lifecycle of';
COMMENT ON COLUMN fims.checks.status IS 'prepared -> released -> encashed | outstanding -> stale; or -> cancelled/voided/spoiled from prepared/released';

CREATE UNIQUE INDEX IF NOT EXISTS uq_checks_bank_account_check_number ON fims.checks (bank_account_id, check_number);
CREATE INDEX IF NOT EXISTS idx_checks_booklet_id ON fims.checks (booklet_id);
CREATE INDEX IF NOT EXISTS idx_checks_status     ON fims.checks (status);
CREATE INDEX IF NOT EXISTS idx_checks_dv_id      ON fims.checks (dv_id);
CREATE INDEX IF NOT EXISTS idx_checks_dv_number  ON fims.checks (dv_number);
CREATE INDEX IF NOT EXISTS idx_checks_check_date ON fims.checks (check_date);
