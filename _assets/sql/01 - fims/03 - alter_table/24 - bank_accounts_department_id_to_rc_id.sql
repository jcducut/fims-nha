-- ============================================================================
-- fims.bank_accounts: department_id (INTEGER, uac.departments FK) -> rc_id
-- (VARCHAR(50), fims.responsibility_code.rc_id) — "Region/Office" is now
-- always keyed by Responsibility Center, same convention already used by
-- fims.budget_allocation.requesting_rc_id (see "03 - alter_table/14 -
-- budget_allocation_requesting_department_id_to_requesting_rc_id.sql").
-- rc_id has no FK, like that column — BankManagementService checks the
-- rc_id exists in fims.responsibility_code before saving.
--
-- Idempotent, same shape as migration 14:
--   * department_id only -> renamed, retyped to VARCHAR(50) (values kept as text)
--   * rc_id only         -> nothing to do (retyped to VARCHAR(50) if not already)
--   * neither            -> rc_id is created
--   * both                -> left alone, with a NOTICE, so no data is ever dropped
-- ============================================================================

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_bank_accounts_department') THEN
        ALTER TABLE fims.bank_accounts DROP CONSTRAINT fk_bank_accounts_department;
    END IF;
END $$;

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
        WHERE table_schema = 'fims' AND table_name = 'bank_accounts' AND column_name = 'department_id'
    ) INTO has_old;
    SELECT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'bank_accounts' AND column_name = 'rc_id'
    ) INTO has_new;

    IF has_old AND NOT has_new THEN
        ALTER TABLE fims.bank_accounts RENAME COLUMN department_id TO rc_id;
        ALTER TABLE fims.bank_accounts ALTER COLUMN rc_id TYPE VARCHAR(50) USING rc_id::VARCHAR(50);
        RAISE NOTICE 'Renamed fims.bank_accounts.department_id to rc_id (VARCHAR(50)).';

        SELECT COUNT(*) INTO orphans
        FROM fims.bank_accounts a
        WHERE a.rc_id IS NOT NULL
          AND NOT EXISTS (SELECT 1 FROM fims.responsibility_code rc WHERE rc.rc_id = a.rc_id);
        IF orphans > 0 THEN
            RAISE NOTICE '% row(s) still hold an old department id in rc_id; it matches no responsibility center, so they show no Region/Office.', orphans;
        END IF;
    ELSIF NOT has_old AND has_new THEN
        SELECT data_type, character_maximum_length INTO new_type, new_len
        FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'bank_accounts' AND column_name = 'rc_id';
        IF new_type <> 'character varying' OR new_len IS DISTINCT FROM 50 THEN
            ALTER TABLE fims.bank_accounts ALTER COLUMN rc_id TYPE VARCHAR(50) USING rc_id::VARCHAR(50);
            RAISE NOTICE 'Retyped fims.bank_accounts.rc_id to VARCHAR(50).';
        END IF;
    ELSIF NOT has_old AND NOT has_new THEN
        ALTER TABLE fims.bank_accounts ADD COLUMN rc_id VARCHAR(50);
        RAISE NOTICE 'Added fims.bank_accounts.rc_id.';
    ELSE
        RAISE NOTICE 'fims.bank_accounts has BOTH department_id and rc_id: left as is, merge them by hand.';
    END IF;

    IF to_regclass('fims.idx_bank_accounts_department_id') IS NOT NULL
       AND to_regclass('fims.idx_bank_accounts_rc_id') IS NULL
       AND has_old AND NOT has_new THEN
        ALTER INDEX fims.idx_bank_accounts_department_id RENAME TO idx_bank_accounts_rc_id;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_bank_accounts_rc_id ON fims.bank_accounts (rc_id);

COMMENT ON COLUMN fims.bank_accounts.rc_id IS 'Region/Office: the Responsibility Center (fims.responsibility_code.rc_id) this bank account is drawn from. Validated against fims.responsibility_code by BankManagementService, never FK-enforced (same convention as fims.budget_allocation.requesting_rc_id).';
