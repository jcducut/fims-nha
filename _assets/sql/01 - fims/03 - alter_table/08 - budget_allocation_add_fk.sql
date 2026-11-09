-- ============================================================
-- ALTER: fims.budget_allocation
-- Foreign keys, added after all referenced tables exist. Postgres has no
-- ADD CONSTRAINT IF NOT EXISTS, so each one is guarded by a DO block.
--
-- Deliberately NOT constrained:
--   * program_id / sub_program_id / project_id — the target tables are keyed by
--     *_code VARCHAR, there is no integer id to reference.
--   * requesting_department_id / requesting_district_id / category_id / user_id —
--     no fims target table; other fims tables keep user/department ids as plain
--     INTEGER too (no cross-schema FK to uac).
-- ============================================================

-- =============================================
-- BUDGET_ALLOCATION CONSTRAINTS
-- =============================================
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_budget_allocation_fiscal_year'
    ) THEN
        ALTER TABLE fims.budget_allocation
        ADD CONSTRAINT fk_budget_allocation_fiscal_year
        FOREIGN KEY (fiscal_year) REFERENCES fims.fiscal_year(fiscal_yr);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_budget_allocation_vendor'
    ) THEN
        ALTER TABLE fims.budget_allocation
        ADD CONSTRAINT fk_budget_allocation_vendor
        FOREIGN KEY (vendor_id) REFERENCES fims.vendors(vendor_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_budget_allocation_parent'
    ) THEN
        ALTER TABLE fims.budget_allocation
        ADD CONSTRAINT fk_budget_allocation_parent
        FOREIGN KEY (parent_id) REFERENCES fims.budget_allocation(allocation_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_budget_allocation_status'
    ) THEN
        ALTER TABLE fims.budget_allocation
        ADD CONSTRAINT fk_budget_allocation_status
        FOREIGN KEY (status_id) REFERENCES fims.settings_status(id);
    END IF;
END $$;
