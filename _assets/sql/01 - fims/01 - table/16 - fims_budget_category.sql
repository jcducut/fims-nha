-- Lookup of budget categories (Infrastructure, Goods and Services, Consultancy).
-- The Category picker on the Memo Request form reads it through BudgetCategoryService.
-- Soft-deleted via deleted_at, same as fims.component_type.
CREATE TABLE IF NOT EXISTS fims.budget_category (
    budget_category_id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_fims_budget_category_deleted_at ON fims.budget_category (deleted_at);

-- Seed: guarded so re-running this file doesn't duplicate rows, since name
-- has no unique constraint (BudgetCategoryService blocks duplicate names
-- instead). Matches on name regardless of deleted_at, so a category that was
-- deleted on purpose is not brought back.
-- INSERT ... SELECT does not promise row order on its own (the planner is free
-- to reorder a VALUES list), so each row carries an ordinal and the SELECT is
-- ORDER BY'd on it: a fresh table gets Infrastructure = 1, Goods and Services = 2,
-- Consultancy = 3.
INSERT INTO fims.budget_category (name)
SELECT v.name
FROM (VALUES
    (1, 'Infrastructure'),
    (2, 'Goods and Services'),
    (3, 'Consultancy')
) AS v(ord, name)
WHERE NOT EXISTS (SELECT 1 FROM fims.budget_category c WHERE c.name = v.name)
ORDER BY v.ord;
