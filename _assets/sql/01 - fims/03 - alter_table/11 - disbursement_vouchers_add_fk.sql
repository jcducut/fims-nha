-- ============================================================
-- ALTER: fims.disbursement_vouchers
-- Foreign keys, added after all referenced tables exist. Postgres has no
-- ADD CONSTRAINT IF NOT EXISTS, so each one is guarded by a DO block.
--
-- Deliberately NOT constrained:
--   * rfa_allocation_id — references fims.budget_allocation, a table whose
--     own CRUD backend doesn't exist yet (see DisbursementVoucherService's
--     read-only RFA lookup). A hard FK here would block deleting/reshaping
--     budget_allocation rows while that feature is still being built.
--   * requesting_department_id — no fims target table; other fims tables
--     keep department ids as plain INTEGER too (no cross-schema FK to uac).
-- ============================================================

-- =============================================
-- DISBURSEMENT_VOUCHERS CONSTRAINTS
-- =============================================
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_disbursement_vouchers_fiscal_yr'
    ) THEN
        ALTER TABLE fims.disbursement_vouchers
        ADD CONSTRAINT fk_disbursement_vouchers_fiscal_yr
        FOREIGN KEY (fiscal_yr) REFERENCES fims.fiscal_year(fiscal_yr);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_disbursement_vouchers_vendor'
    ) THEN
        ALTER TABLE fims.disbursement_vouchers
        ADD CONSTRAINT fk_disbursement_vouchers_vendor
        FOREIGN KEY (vendor_id) REFERENCES fims.vendors(vendor_id);
    END IF;
END $$;
