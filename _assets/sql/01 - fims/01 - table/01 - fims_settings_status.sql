CREATE SCHEMA IF NOT EXISTS fims;

-- Shared status lookup, scoped per entity via status_for (e.g. 'fiscal_year').
-- Other fims tables' status_id columns reference this instead of a free-text column.
-- actors (renamed to next_actors by "03 - alter_table/21 - settings_status_rename_actors_to_next_actors.sql"):
-- who can act on a record while it's in this status, e.g.
--   {"users": [], "userLevel": "Department Manager", "department": "Office of the General Manager"}
-- "users" lists specific user ids (overrides/supplements userLevel+department when non-empty).
CREATE TABLE IF NOT EXISTS fims.settings_status (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    status_for VARCHAR(255),
    color VARCHAR(7),
    actors JSONB
);

CREATE INDEX IF NOT EXISTS idx_settings_status_name ON fims.settings_status (name);
CREATE INDEX IF NOT EXISTS idx_settings_status_status_for ON fims.settings_status (status_for);
CREATE INDEX IF NOT EXISTS idx_settings_status_composite ON fims.settings_status (name, status_for);
