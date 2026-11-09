-- Seed data (matches shared.coe_category_type) — guarded so re-running this
-- file doesn't duplicate rows, since expense_class_code has no unique constraint.
INSERT INTO fims.expense_class (expense_class_code, expense_class_name)
SELECT 'CO', 'Capital Outlay'
WHERE NOT EXISTS (SELECT 1 FROM fims.expense_class WHERE expense_class_code = 'CO');

INSERT INTO fims.expense_class (expense_class_code, expense_class_name)
SELECT 'PS', 'Personnel Services'
WHERE NOT EXISTS (SELECT 1 FROM fims.expense_class WHERE expense_class_code = 'PS');

INSERT INTO fims.expense_class (expense_class_code, expense_class_name)
SELECT 'MOOE', 'Maintenance and Other Operating Expenses'
WHERE NOT EXISTS (SELECT 1 FROM fims.expense_class WHERE expense_class_code = 'MOOE');

INSERT INTO fims.expense_class (expense_class_code, expense_class_name)
SELECT 'EO', 'Equipment Outlay'
WHERE NOT EXISTS (SELECT 1 FROM fims.expense_class WHERE expense_class_code = 'EO');
