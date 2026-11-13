-- ============================================================
-- ALTER: fims.bank_accounts / check_booklets / checks /
-- check_cancellations / check_claimant_releases /
-- check_claimant_email_log / check_region_releases — foreign keys for the
-- Check Disbursement Voucher module's tables, added after all referenced
-- tables exist. Postgres has no ADD CONSTRAINT IF NOT EXISTS, so each one
-- is guarded by a DO block (same pattern as
-- "11 - disbursement_vouchers_add_fk.sql" above).
--
-- Deliberately NOT constrained:
--   * check_region_releases.region_dept — free text, not an FK to
--     uac.departments (see that table's own header comment).
--   * *.created_by / *.cancelled_by / *.sent_by / *.prepared_by — plain
--     INTEGER, no cross-schema FK to uac.users (same convention as
--     fims.disbursement_vouchers.created_by).
-- ============================================================

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_check_booklets_bank_account') THEN
        ALTER TABLE fims.check_booklets
        ADD CONSTRAINT fk_check_booklets_bank_account
        FOREIGN KEY (bank_account_id) REFERENCES fims.bank_accounts(bank_account_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_checks_bank_account') THEN
        ALTER TABLE fims.checks
        ADD CONSTRAINT fk_checks_bank_account
        FOREIGN KEY (bank_account_id) REFERENCES fims.bank_accounts(bank_account_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_checks_booklet') THEN
        ALTER TABLE fims.checks
        ADD CONSTRAINT fk_checks_booklet
        FOREIGN KEY (booklet_id) REFERENCES fims.check_booklets(booklet_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_checks_dv') THEN
        ALTER TABLE fims.checks
        ADD CONSTRAINT fk_checks_dv
        FOREIGN KEY (dv_id) REFERENCES fims.disbursement_vouchers(dv_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_check_cancellations_check') THEN
        ALTER TABLE fims.check_cancellations
        ADD CONSTRAINT fk_check_cancellations_check
        FOREIGN KEY (check_id) REFERENCES fims.checks(check_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_check_cancellations_replacement') THEN
        ALTER TABLE fims.check_cancellations
        ADD CONSTRAINT fk_check_cancellations_replacement
        FOREIGN KEY (replacement_check_id) REFERENCES fims.checks(check_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_check_claimant_releases_check') THEN
        ALTER TABLE fims.check_claimant_releases
        ADD CONSTRAINT fk_check_claimant_releases_check
        FOREIGN KEY (check_id) REFERENCES fims.checks(check_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_check_claimant_email_log_release') THEN
        ALTER TABLE fims.check_claimant_email_log
        ADD CONSTRAINT fk_check_claimant_email_log_release
        FOREIGN KEY (release_id) REFERENCES fims.check_claimant_releases(release_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_check_claimant_email_log_check') THEN
        ALTER TABLE fims.check_claimant_email_log
        ADD CONSTRAINT fk_check_claimant_email_log_check
        FOREIGN KEY (check_id) REFERENCES fims.checks(check_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_check_region_releases_check') THEN
        ALTER TABLE fims.check_region_releases
        ADD CONSTRAINT fk_check_region_releases_check
        FOREIGN KEY (check_id) REFERENCES fims.checks(check_id);
    END IF;
END $$;
