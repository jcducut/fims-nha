-- Seed data for fims.settings_status, scoped to status_for = 'fiscal_year'
-- (Open = currently accepting transactions, Closed = locked). Guarded with
-- WHERE NOT EXISTS since settings_status has no unique constraint on
-- (name, status_for) to key an ON CONFLICT off of.
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Open', 'fiscal_year', '#16A34A'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Open' AND status_for = 'fiscal_year');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Closed', 'fiscal_year', '#6B7280'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Closed' AND status_for = 'fiscal_year');

-- status_for = 'budget_req' (fims.budget_req's workflow status)
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Draft', 'budget_req', '#6B7280'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Draft' AND status_for = 'budget_req');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'For Review', 'budget_req', '#2563EB'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'For Review' AND status_for = 'budget_req');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'For Approval', 'budget_req', '#D97706'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'For Approval' AND status_for = 'budget_req');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Returned', 'budget_req', '#F97316'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Returned' AND status_for = 'budget_req');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Cancelled', 'budget_req', '#DC3545'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Cancelled' AND status_for = 'budget_req');

-- Set by Budget Processing's "Endorse" action: the Budget Officer has filled in
-- every line item's Type of Fund + Component and endorsed the request onward.
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Endorsed', 'budget_req', '#0D9488'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Endorsed' AND status_for = 'budget_req');

-- Set by Budget Preview's "Approve" action: the Budget Head has approved an
-- Endorsed request.
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Approved', 'budget_req', '#16A34A'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Approved' AND status_for = 'budget_req');
