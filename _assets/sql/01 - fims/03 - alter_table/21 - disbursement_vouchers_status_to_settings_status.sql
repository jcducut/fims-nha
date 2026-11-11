-- ALTER: fims.disbursement_vouchers
-- Moves the voucher's four-office pipeline status off a free-text column onto the shared
-- fims.settings_status lookup (status_for = 'disbursement_voucher', seeded in
-- "02 - sample_data/01 - fims_settings_status.sql"), same convention fims.budget_req already uses
-- (see BudgetReqService::getStatusIdByName()). The status NAMES themselves are unchanged
-- (DRAFT/FOR_FMD_REVIEW/FOR_ACCOUNTING/FOR_TREASURY/RELEASED/REJECTED/CANCELLED) — only where they
-- live moves, so DisbursementVoucherService::TRANSITIONS/STAGE_HOLDER_RC and every route/frontend
-- comparison against those strings keep working unchanged; the service resolves status_id <-> name
-- via DisbursementVoucherService::getStatusIdByName()/getStatusNameById().

ALTER TABLE fims.disbursement_vouchers
ADD COLUMN IF NOT EXISTS status_id INTEGER REFERENCES fims.settings_status(id);

-- Backfill from the old text column — runs before it's dropped below, so any row inserted by an
-- earlier phase (table creation's own DEFAULT 'DRAFT', or any seed in "02 - sample_data"/"02 -
-- seed_data") still ends up with the right status_id.
UPDATE fims.disbursement_vouchers dv
SET status_id = st.id
FROM fims.settings_status st
WHERE st.status_for = 'disbursement_voucher' AND st.name = dv.status AND dv.status_id IS NULL;

DROP INDEX IF EXISTS fims.idx_disbursement_vouchers_status;

ALTER TABLE fims.disbursement_vouchers
DROP COLUMN IF EXISTS status;

CREATE INDEX IF NOT EXISTS idx_disbursement_vouchers_status_id ON fims.disbursement_vouchers (status_id);

COMMENT ON COLUMN fims.disbursement_vouchers.status_id IS 'fims.settings_status.id WHERE status_for = ''disbursement_voucher'' — see DisbursementVoucherService::TRANSITIONS for the pipeline this status_id drives';
