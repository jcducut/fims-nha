-- ALTER: fims.deduction_types, fims.atc_codes
-- Each deduction type / ATC code can carry a default GL account (fims.chart_of_accounts) it posts
-- to — the Accounting Entries grid (app/pages/voucher/readone.php, prefix vje_) auto-fills a
-- credit line for any Deduction or ATC row whose type/code has this set, alongside the manual
-- Dr line Accounting still enters. Nullable: a deduction type / ATC code with no account_code
-- simply isn't auto-filled (its amount still reduces net_amount, Accounting just has to post it
-- manually, same as before this column existed).
ALTER TABLE fims.deduction_types
ADD COLUMN IF NOT EXISTS account_code VARCHAR(30);

COMMENT ON COLUMN fims.deduction_types.account_code IS 'Default GL account this deduction type posts to (credit) when the Accounting Entries grid auto-fills from it; nullable';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_deduction_types_account_code'
    ) THEN
        ALTER TABLE fims.deduction_types
        ADD CONSTRAINT fk_deduction_types_account_code
        FOREIGN KEY (account_code) REFERENCES fims.chart_of_accounts(account_code);
    END IF;
END $$;

-- Retention withheld from a contractor is a liability until released — Guaranty/Security Deposits
-- Payable, the same account fims.sl_masters.'SL-RETENTION-01' already breaks down by SL.
UPDATE fims.deduction_types SET account_code = '2-04-01-040' WHERE code = 'RETENTION';

ALTER TABLE fims.atc_codes
ADD COLUMN IF NOT EXISTS account_code VARCHAR(30);

COMMENT ON COLUMN fims.atc_codes.account_code IS 'Default GL liability account this ATC''s withholding posts to (credit) when the Accounting Entries grid auto-fills from it; nullable';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_atc_codes_account_code'
    ) THEN
        ALTER TABLE fims.atc_codes
        ADD CONSTRAINT fk_atc_codes_account_code
        FOREIGN KEY (account_code) REFERENCES fims.chart_of_accounts(account_code);
    END IF;
END $$;

-- All of this project's seeded ATC codes are BIR withholding — they all post to the same Due to
-- BIR liability, same account fims.sl_masters.'SL-DUEBIR-WTAX' already breaks down by SL.
UPDATE fims.atc_codes SET account_code = '2-02-01-010' WHERE account_code IS NULL;
