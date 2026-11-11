CREATE TABLE IF NOT EXISTS fims.fiscal_year (
    fiscal_yr SMALLINT PRIMARY KEY,
    status_id INTEGER REFERENCES fims.settings_status(id)
);
