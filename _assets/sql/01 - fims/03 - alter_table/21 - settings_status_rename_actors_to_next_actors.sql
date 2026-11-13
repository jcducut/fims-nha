-- ============================================================
-- ALTER: fims.settings_status
-- Renames actors -> next_actors (the JSONB column created by "01 - table/01 - fims_settings_status.sql"):
-- it names who acts NEXT on a record that has reached this status, and the new name says so.
-- The values are kept as they are -- same JSONB shape, e.g.
--   {"users": [], "userLevel": "Department Manager", "department": "Office of the General Manager"}
--
-- Nothing in app/ reads or writes the column yet, and no index, view, function or constraint depends on it.
--
-- Idempotent:
--   * actors only             -> renamed (values are kept)
--   * next_actors only        -> nothing to do
--   * neither                 -> next_actors is created (JSONB, nullable, like the original column)
--   * both (partial manual fix) -> left alone, with a NOTICE, so no data is ever dropped
-- ============================================================

DO $$
DECLARE
    has_old BOOLEAN;
    has_new BOOLEAN;
BEGIN
    SELECT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'settings_status' AND column_name = 'actors'
    ) INTO has_old;
    SELECT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'settings_status' AND column_name = 'next_actors'
    ) INTO has_new;

    IF has_old AND NOT has_new THEN
        ALTER TABLE fims.settings_status RENAME COLUMN actors TO next_actors;
        RAISE NOTICE 'Renamed fims.settings_status.actors to next_actors.';
    ELSIF NOT has_old AND NOT has_new THEN
        ALTER TABLE fims.settings_status ADD COLUMN next_actors JSONB;
        RAISE NOTICE 'Added fims.settings_status.next_actors.';
    ELSIF has_old AND has_new THEN
        RAISE NOTICE 'fims.settings_status has BOTH actors and next_actors: left as is, merge them by hand.';
    END IF;
END $$;

COMMENT ON COLUMN fims.settings_status.next_actors IS
    'Who acts next on a record that has reached this status (renamed from actors). JSONB, e.g. {"users": [], "userLevel": "Department Manager", "department": "Office of the General Manager"}; a non-empty "users" list of user ids overrides/supplements userLevel + department.';
