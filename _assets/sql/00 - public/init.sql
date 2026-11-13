-- uuid-ossp is NOT used by any SQL in this repo (UUIDs are generated in PHP, and the
-- UUID column type is built into PostgreSQL). It is created only as a convenience, so a
-- machine that cannot load the extension library (e.g. Windows Application Control
-- blocking "uuid-ossp.dll") must not stop the install: the failure is reported as a
-- NOTICE and the rest of the SQL still runs.
DROP EXTENSION IF EXISTS "uuid-ossp" CASCADE;

DO $$
BEGIN
    CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;
EXCEPTION WHEN OTHERS THEN
    RAISE NOTICE 'Skipping the optional "uuid-ossp" extension: %', SQLERRM;
END
$$;
