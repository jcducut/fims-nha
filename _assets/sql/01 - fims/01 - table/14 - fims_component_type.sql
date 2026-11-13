-- Lookup of component types (Capital Outlay, Other-Capital Outlay, COE).
-- Referenced by fims.budget_line_items.component_id.
-- Soft-deleted via deleted_at, same as fims.expense_class.
CREATE TABLE IF NOT EXISTS fims.component_type (
    component_type_id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_fims_component_type_deleted_at ON fims.component_type (deleted_at);

-- Seed: guarded so re-running this file doesn't duplicate rows, since name
-- has no unique constraint. Inserted in this order, so a fresh table gets
-- ids 1-3.
INSERT INTO fims.component_type (name)
SELECT v.name
FROM (VALUES
    ('CAPITAL OUTLAY'),
    ('OTHER-CAPITAL OUTLAY'),
    ('COE')
) AS v(name)
WHERE NOT EXISTS (SELECT 1 FROM fims.component_type c WHERE c.name = v.name);
