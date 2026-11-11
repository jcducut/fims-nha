-- ALTER: fims.disbursement_vouchers, fims.journal_entry_voucher_lines
-- Replaces the DV's old single-value tax fields with repeating tables, and adds Subsidiary Ledger
-- columns to the Journal Entry grid:
--   - vat_rate/vat_amount/wtax_rate/wtax_amount -> fims.disbursement_voucher_deductions (non-tax
--     deductions like retention) + fims.disbursement_voucher_atc_lines (BIR withholding, now
--     multi-row) — see "01 - table/21" and "01 - table/22".
--   - atc_code -> fims.disbursement_voucher_atc_lines.atc_code (one DV can now withhold under
--     more than one ATC at once).
--   - net_amount was GENERATED ALWAYS AS (gross - vat - wtax); it's now a service-computed
--     snapshot (gross_amount - total deductions - total withholding tax), recomputed by
--     DisbursementVoucherService whenever the voucher, its deductions, or its ATC lines change —
--     same "validate/compute in PHP, snapshot in the column" convention as
--     payments_prior_amount/balance_amount (see
--     "03 - alter_table/16 - disbursement_vouchers_add_payments_prior.sql"). It can no longer be
--     a generated column because deductions/ATC withholding now live in separate tables a
--     generated-column expression can't reference.
--
-- fims.journal_entry_voucher_lines gets sl_code/sl_detail_id (see "01 - table/23"/"24") — the
-- paper form's "SL Code"/"SL Detail" columns on the Accounting Entries grid, alongside the
-- existing account_code. Both nullable: not every line needs a subsidiary-ledger breakdown.

-- ── journal_entry_voucher_lines: add SL columns ──
ALTER TABLE fims.journal_entry_voucher_lines
ADD COLUMN IF NOT EXISTS sl_code      VARCHAR(30),
ADD COLUMN IF NOT EXISTS sl_detail_id INTEGER;

COMMENT ON COLUMN fims.journal_entry_voucher_lines.sl_code      IS 'Subsidiary Ledger master (fims.sl_masters) this line breaks down, if any';
COMMENT ON COLUMN fims.journal_entry_voucher_lines.sl_detail_id IS 'Subsidiary Ledger detail (fims.sl_details) this line breaks down, if any; must belong to sl_code when both are set (service-enforced)';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_journal_entry_voucher_lines_sl_code'
    ) THEN
        ALTER TABLE fims.journal_entry_voucher_lines
        ADD CONSTRAINT fk_journal_entry_voucher_lines_sl_code
        FOREIGN KEY (sl_code) REFERENCES fims.sl_masters(sl_code);
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_journal_entry_voucher_lines_sl_detail_id'
    ) THEN
        ALTER TABLE fims.journal_entry_voucher_lines
        ADD CONSTRAINT fk_journal_entry_voucher_lines_sl_detail_id
        FOREIGN KEY (sl_detail_id) REFERENCES fims.sl_details(sl_detail_id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_journal_entry_voucher_lines_sl_code      ON fims.journal_entry_voucher_lines (sl_code);
CREATE INDEX IF NOT EXISTS idx_journal_entry_voucher_lines_sl_detail_id ON fims.journal_entry_voucher_lines (sl_detail_id);

-- ── disbursement_vouchers: net_amount becomes a plain snapshot column ──
ALTER TABLE fims.disbursement_vouchers
ALTER COLUMN net_amount DROP EXPRESSION IF EXISTS;

COMMENT ON COLUMN fims.disbursement_vouchers.net_amount IS 'gross_amount - total deductions (fims.disbursement_voucher_deductions) - total withholding tax (fims.disbursement_voucher_atc_lines); service-computed snapshot, not a generated column';

-- ── disbursement_vouchers: drop the old single-value tax fields ──
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_disbursement_vouchers_atc_code') THEN
        ALTER TABLE fims.disbursement_vouchers DROP CONSTRAINT fk_disbursement_vouchers_atc_code;
    END IF;
END $$;

DROP INDEX IF EXISTS fims.idx_disbursement_vouchers_atc_code;

ALTER TABLE fims.disbursement_vouchers
DROP COLUMN IF EXISTS vat_rate,
DROP COLUMN IF EXISTS vat_amount,
DROP COLUMN IF EXISTS wtax_rate,
DROP COLUMN IF EXISTS wtax_amount,
DROP COLUMN IF EXISTS atc_code;
