-- ============================================================
-- ALTER: fims.budget_allocation
-- requesting_department_id (INTEGER, a uac.departments id)  ->  requesting_rc_id (VARCHAR(50)).
-- The requesting office of a memo request is now the Responsibility Center of the user who
-- created it: uac.users.rc_id, which is a fims.responsibility_code.rc_id (e.g. 'RC-027').
-- "01 - table/15 - fims_budget_allocation.sql" creates the new column on a fresh install; this
-- file brings an existing database up to date.
--
-- Idempotent:
--   * requesting_department_id only -> renamed, retyped to VARCHAR(50) (values are kept as text)
--   * requesting_rc_id only         -> nothing to do (retyped to VARCHAR(50) if it is not already)
--   * neither                       -> requesting_rc_id is created
--   * both (15 re-ran first)        -> left alone, with a NOTICE, so no data is ever dropped
--
-- Old values were uac.departments ids, which mean nothing as an rc_id: after the rename a row that
-- had one keeps it as text but matches no responsibility center, so it simply shows no Requesting
-- Office. The NOTICE below says how many rows that is. The column has no foreign key, like the
-- other requesting_* column; BudgetAllocationService checks the rc_id exists.
-- ============================================================

DO $$
DECLARE
    has_old   BOOLEAN;
    has_new   BOOLEAN;
    new_type  TEXT;
    new_len   INTEGER;
    orphans   BIGINT;
BEGIN
    SELECT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'budget_allocation' AND column_name = 'requesting_department_id'
    ) INTO has_old;
    SELECT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'budget_allocation' AND column_name = 'requesting_rc_id'
    ) INTO has_new;

    IF has_old AND NOT has_new THEN
        ALTER TABLE fims.budget_allocation RENAME COLUMN requesting_department_id TO requesting_rc_id;
        ALTER TABLE fims.budget_allocation ALTER COLUMN requesting_rc_id TYPE VARCHAR(50) USING requesting_rc_id::VARCHAR(50);
        RAISE NOTICE 'Renamed fims.budget_allocation.requesting_department_id to requesting_rc_id (VARCHAR(50)).';

        SELECT COUNT(*) INTO orphans
        FROM fims.budget_allocation a
        WHERE a.requesting_rc_id IS NOT NULL
          AND NOT EXISTS (SELECT 1 FROM fims.responsibility_code rc WHERE rc.rc_id = a.requesting_rc_id);
        IF orphans > 0 THEN
            RAISE NOTICE '% row(s) still hold an old department id in requesting_rc_id; it matches no responsibility center, so they show no Requesting Office.', orphans;
        END IF;
    ELSIF NOT has_old AND has_new THEN
        SELECT data_type, character_maximum_length INTO new_type, new_len
        FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'budget_allocation' AND column_name = 'requesting_rc_id';
        IF new_type <> 'character varying' OR new_len IS DISTINCT FROM 50 THEN
            ALTER TABLE fims.budget_allocation ALTER COLUMN requesting_rc_id TYPE VARCHAR(50) USING requesting_rc_id::VARCHAR(50);
            RAISE NOTICE 'Retyped fims.budget_allocation.requesting_rc_id to VARCHAR(50).';
        END IF;
    ELSIF NOT has_old AND NOT has_new THEN
        ALTER TABLE fims.budget_allocation ADD COLUMN requesting_rc_id VARCHAR(50);
        RAISE NOTICE 'Added fims.budget_allocation.requesting_rc_id.';
    ELSE
        RAISE NOTICE 'fims.budget_allocation has BOTH requesting_department_id and requesting_rc_id: left as is, merge them by hand.';
    END IF;

    -- the index follows the column
    IF to_regclass('fims.idx_budget_allocation_requesting_department') IS NOT NULL
       AND to_regclass('fims.idx_budget_allocation_requesting_rc') IS NULL
       AND has_old AND NOT has_new THEN
        ALTER INDEX fims.idx_budget_allocation_requesting_department RENAME TO idx_budget_allocation_requesting_rc;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_budget_allocation_requesting_rc ON fims.budget_allocation (requesting_rc_id);

COMMENT ON COLUMN fims.budget_allocation.requesting_rc_id IS 'Requesting office: the Responsibility Center (fims.responsibility_code.rc_id) of the user who created the allocation. Set by the API from the logged-in user (uac.users.rc_id), never from the request body.';
