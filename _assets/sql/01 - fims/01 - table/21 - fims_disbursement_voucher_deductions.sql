-- Non-tax deduction lines on a disbursement voucher (e.g. contractor retention) — entered by
-- Originating at DV creation, same stage/author as the voucher's gross_amount itself. Replaces
-- the old single vat_rate/vat_amount/wtax_rate/wtax_amount pair of columns that used to live
-- directly on fims.disbursement_vouchers (dropped in
-- "03 - alter_table/17 - disbursement_vouchers_drop_tax_fields_add_sl.sql"); BIR withholding tax
-- is now its own separate repeating section — see
-- "22 - fims_disbursement_voucher_atc_lines.sql" — prepared later by Accounting.
--
-- deduction_amount = gross_amount * rate / 100 when rate is given, otherwise it's typed directly
-- (e.g. a flat-peso deduction with no percentage) — enforced in
-- DisbursementVoucherService::prepare(), not a DB constraint, same validate-in-PHP convention
-- already used for fims.journal_entry_voucher_lines' debit/credit.
CREATE TABLE IF NOT EXISTS fims.disbursement_voucher_deductions (
    dv_deduction_id    SERIAL PRIMARY KEY,
    dv_id              INTEGER NOT NULL REFERENCES fims.disbursement_vouchers(dv_id) ON DELETE CASCADE,
    line_number        INTEGER NOT NULL,
    deduction_type_id  INTEGER NOT NULL REFERENCES fims.deduction_types(deduction_type_id),
    rate               NUMERIC(5,2),
    gross_amount       NUMERIC(18,2) NOT NULL DEFAULT 0,
    deduction_amount   NUMERIC(18,2) NOT NULL DEFAULT 0,
    UNIQUE (dv_id, line_number)
);

COMMENT ON TABLE  fims.disbursement_voucher_deductions IS 'Non-tax deduction lines on a DV (e.g. retention), entered by Originating at creation; total feeds the voucher''s net_amount snapshot';
COMMENT ON COLUMN fims.disbursement_voucher_deductions.rate IS 'Percent applied to gross_amount to get deduction_amount; NULL when the amount was typed directly instead';

CREATE INDEX IF NOT EXISTS idx_disbursement_voucher_deductions_dv_id             ON fims.disbursement_voucher_deductions (dv_id);
CREATE INDEX IF NOT EXISTS idx_disbursement_voucher_deductions_deduction_type_id ON fims.disbursement_voucher_deductions (deduction_type_id);
