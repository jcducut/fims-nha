-- Lookup for nature of fund classification (Insertions, Equity, GAAAO,
-- Unprogrammed, Supplemental). Referenced by fims.fund_tracking.nature_of_fund_id.
CREATE TABLE IF NOT EXISTS fims.nature_of_fund (
    nature_of_fund_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT nature_of_fund_name_key UNIQUE (name)
);

-- Seed: name is unique, so ON CONFLICT keeps re-runs from duplicating rows.
INSERT INTO fims.nature_of_fund (name) VALUES
    ('Insertions'),
    ('Equity'),
    ('GAAAO'),
    ('Unprogrammed'),
    ('SUPPLEMENTAL')
ON CONFLICT (name) DO NOTHING;
