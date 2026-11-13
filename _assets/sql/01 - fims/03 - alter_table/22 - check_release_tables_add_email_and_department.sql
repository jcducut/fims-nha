-- ============================================================================
-- fims.check_claimant_releases / fims.check_region_releases — adds the
-- columns needed to wire real SMTP email notices (CheckProcessingService's
-- sendReleaseNotice/sendRegionReleaseNotice):
--
--   * check_claimant_releases.claimant_email — captured when a release is
--     recorded as release_type = EMAIL_NOTICE (see the Record Check Release
--     modal); "Send Email Notice" sends to this address.
--   * check_region_releases.department_id — the frontend's own "New Region
--     Release" modal already posts a department_id (not free text), so this
--     backs that with a real FK to uac.departments. region_dept (added in
--     "01 - table/32 - fims_check_region_release.sql") stays as a snapshot
--     of the department's name/acronym at release time, same convention as
--     disbursement_vouchers.rfa_allocation_number snapshotting
--     fims.budget_allocation — so a later department rename/merge doesn't
--     change what historical releases display.
--   * check_region_email_log.department_id — so resendRegionEmail() can
--     re-derive which department's currently-PENDING releases to bundle
--     without having to reverse-parse the region_dept text snapshot.
-- ============================================================================

ALTER TABLE fims.check_claimant_releases
ADD COLUMN IF NOT EXISTS claimant_email VARCHAR(150);

ALTER TABLE fims.check_region_releases
ADD COLUMN IF NOT EXISTS department_id INTEGER;

ALTER TABLE fims.check_region_email_log
ADD COLUMN IF NOT EXISTS department_id INTEGER;

CREATE INDEX IF NOT EXISTS idx_check_region_releases_department_id ON fims.check_region_releases (department_id);
CREATE INDEX IF NOT EXISTS idx_check_region_email_log_department_id ON fims.check_region_email_log (department_id);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_check_region_releases_department') THEN
        ALTER TABLE fims.check_region_releases
        ADD CONSTRAINT fk_check_region_releases_department
        FOREIGN KEY (department_id) REFERENCES uac.departments(department_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_check_region_email_log_department') THEN
        ALTER TABLE fims.check_region_email_log
        ADD CONSTRAINT fk_check_region_email_log_department
        FOREIGN KEY (department_id) REFERENCES uac.departments(department_id);
    END IF;
END $$;
