-- ============================================================
-- ALTER: fims.budget_allocation
-- Foreign keys, added after all referenced tables exist. Postgres has no
-- ADD CONSTRAINT IF NOT EXISTS, so each one is guarded by a DO block.
--
-- Deliberately NOT constrained:
--   * requesting_district_id / category_id / user_id —
--     no fims target table; other fims tables keep user/department ids as plain
--     INTEGER too (no cross-schema FK to uac). BudgetAllocationService validates
--     the district id against uac.departments instead.
--   * requesting_rc_id (VARCHAR(50), the creator's fims.responsibility_code.rc_id) —
--     no FK either; BudgetAllocationService checks that the rc_id exists.
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
        WHERE conname = 'fk_budget_allocation_program'
    ) THEN
        ALTER TABLE fims.budget_allocation
        ADD CONSTRAINT fk_budget_allocation_program
        FOREIGN KEY (program_code) REFERENCES fims.program(program_code);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_budget_allocation_sub_program'
    ) THEN
        ALTER TABLE fims.budget_allocation
        ADD CONSTRAINT fk_budget_allocation_sub_program
        FOREIGN KEY (sub_program_code) REFERENCES fims.sub_program(sub_program_code);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_budget_allocation_project'
    ) THEN
        ALTER TABLE fims.budget_allocation
        ADD CONSTRAINT fk_budget_allocation_project
        FOREIGN KEY (project_code) REFERENCES fims.project(project_code);
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
