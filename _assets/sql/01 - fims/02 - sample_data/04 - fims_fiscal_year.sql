-- Seed data for fims.fiscal_year — fiscal_yr is the PK itself, so ON CONFLICT
-- makes re-running this file a no-op.
INSERT INTO fims.fiscal_year (fiscal_yr) VALUES
    (2024),
    (2025),
    (2026),
    (2027),
    (2028)
ON CONFLICT (fiscal_yr) DO NOTHING;
