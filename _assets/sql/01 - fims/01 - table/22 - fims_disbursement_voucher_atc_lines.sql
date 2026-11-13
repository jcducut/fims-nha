-- BIR withholding tax classification lines on a disbursement voucher — replaces the old single
-- fims.disbursement_vouchers.atc_code column (dropped in
-- "03 - alter_table/17 - disbursement_vouchers_drop_tax_fields_add_sl.sql"). A DV can withhold
-- under more than one ATC at once (e.g. a mixed goods-and-services payment), so this is a
-- repeating table like fims.journal_entry_voucher_lines, not a single column. Prepared by
-- Accounting during the FOR_ACCOUNTING stage, same author/stage as the Journal Entry itself (see
-- DisbursementVoucherService::saveJournalEntry()) — this table and the JEV lines are saved
-- together in that one call.
--
-- withholding_tax = tax_base * rate / 100, enforced in DisbursementVoucherService, not a DB
-- constraint, same validate-in-PHP convention as fims.journal_entry_voucher_lines. chargeability
-- is the Responsibility Center the withheld-tax liability is booked against (e.g. '012'
-- Accounting Dept) — nullable since not every withholding needs a specific RC override.
CREATE TABLE IF NOT EXISTS fims.disbursement_voucher_atc_lines (
    dv_atc_line_id   SERIAL PRIMARY KEY,
    dv_id            INTEGER NOT NULL REFERENCES fims.disbursement_vouchers(dv_id) ON DELETE CASCADE,
    line_number      INTEGER NOT NULL,
    atc_code         VARCHAR(10) NOT NULL REFERENCES fims.atc_codes(atc_code),
    rate             NUMERIC(5,2) NOT NULL DEFAULT 0,
    tax_base         NUMERIC(18,2) NOT NULL DEFAULT 0,
    withholding_tax  NUMERIC(18,2) NOT NULL DEFAULT 0,
    chargeability    VARCHAR(10) REFERENCES fims.responsibility_code(rc_id),
    UNIQUE (dv_id, line_number)
);

COMMENT ON TABLE  fims.disbursement_voucher_atc_lines IS 'BIR withholding tax classification lines on a DV, prepared by Accounting alongside the Journal Entry; one DV can have several ATC lines';
COMMENT ON COLUMN fims.disbursement_voucher_atc_lines.chargeability IS 'Responsibility Center (fims.responsibility_code.rc_id) the withheld-tax liability is booked against; optional';

CREATE INDEX IF NOT EXISTS idx_disbursement_voucher_atc_lines_dv_id         ON fims.disbursement_voucher_atc_lines (dv_id);
CREATE INDEX IF NOT EXISTS idx_disbursement_voucher_atc_lines_atc_code     ON fims.disbursement_voucher_atc_lines (atc_code);
CREATE INDEX IF NOT EXISTS idx_disbursement_voucher_atc_lines_chargeability ON fims.disbursement_voucher_atc_lines (chargeability);
