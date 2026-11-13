-- Lookup for agencies (e.g. the source agency of a fund tracking record).
CREATE TABLE IF NOT EXISTS fims.agency (
    agency_id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT agency_name_key UNIQUE (name)
);
