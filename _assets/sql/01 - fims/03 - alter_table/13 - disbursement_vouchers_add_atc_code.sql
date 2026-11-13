-- ALTER: fims.disbursement_vouchers
-- Accounting tags the voucher with a BIR Alphanumeric Tax Code when it prepares
-- the Journal Entry Voucher (see "18 - fims_journal_entry_vouchers.sql"); only
-- required when the voucher actually withholds (vat_amount + wtax_amount > 0),
-- enforced in DisbursementVoucherService::saveJournalEntry(), not here.
ALTER TABLE fims.disbursement_vouchers
ADD COLUMN IF NOT EXISTS atc_code VARCHAR(10);

COMMENT ON COLUMN fims.disbursement_vouchers.atc_code IS 'BIR Alphanumeric Tax Code (fims.atc_codes) applied to this voucher''s withholding, set by Accounting; required only when vat_amount + wtax_amount > 0';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_disbursement_vouchers_atc_code'
    ) THEN
        ALTER TABLE fims.disbursement_vouchers
        ADD CONSTRAINT fk_disbursement_vouchers_atc_code
        FOREIGN KEY (atc_code) REFERENCES fims.atc_codes(atc_code);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_disbursement_vouchers_atc_code ON fims.disbursement_vouchers (atc_code);
