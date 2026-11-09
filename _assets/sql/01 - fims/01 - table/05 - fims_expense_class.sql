-- Mirrors shared.coe_category_type (govflow360_nha) — surrogate id as PK,
-- expense_class_code is a plain indexed lookup field, not unique/PK.
CREATE TABLE IF NOT EXISTS fims.expense_class (
    expense_class_id SERIAL PRIMARY KEY,
    expense_class_code VARCHAR(50) NOT NULL,
    expense_class_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ(6) DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ(6) DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ(6)
);

CREATE INDEX IF NOT EXISTS idx_fims_expense_class_code ON fims.expense_class (expense_class_code);
CREATE INDEX IF NOT EXISTS idx_fims_expense_class_deleted_at ON fims.expense_class (deleted_at);
