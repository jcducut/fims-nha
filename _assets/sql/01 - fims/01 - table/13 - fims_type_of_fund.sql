-- Lookup of fund types (NG Support - GAA / NF / UF, Trust Fund, Corporate
-- Receipt, ...). Referenced by fims.budget_line_items.type_of_fund_id.
-- Soft-deleted via deleted_at, same as fims.expense_class.
CREATE TABLE IF NOT EXISTS fims.type_of_fund (
    type_of_fund_id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_fims_type_of_fund_deleted_at ON fims.type_of_fund (deleted_at);

-- Seed: guarded so re-running this file doesn't duplicate rows, since name
-- has no unique constraint. Inserted in this order, so a fresh table gets
-- ids 1-5.
INSERT INTO fims.type_of_fund (name)
SELECT v.name
FROM (VALUES
    ('NG SUPPORT - GAA'),
    ('NG SUPPORT - NF'),
    ('NG SUPPORT - UF'),
    ('TRUST FUND'),
    ('CORPORATE RECEIPT')
) AS v(name)
WHERE NOT EXISTS (SELECT 1 FROM fims.type_of_fund t WHERE t.name = v.name);
