-- ============================================================================
-- Bank Accounts + Check Booklets — sample data so the Check Disbursement
-- Voucher module's "Prepare Check" modal has real Bank Account and (once
-- selected) Check Number options (CheckProcessingService::getBankAccounts()
-- / getUnusedChecks()). Each booklet's start/end check numbers are
-- zero-padded digit strings (e.g. '0000001') — getUnusedChecks() generates
-- the full range and excludes any already used in fims.checks.
-- ============================================================================

INSERT INTO fims.bank_accounts (account_name, account_number, bank_name, office_type, department_type, is_active)
SELECT v.account_name, v.account_number, v.bank_name, v.office_type, v.department_type, true
FROM (VALUES
    ('NHA General Fund - Main',       '1461-1234-56', 'Land Bank of the Philippines', 'main',   'main'),
    ('NHA Regional Disbursing - R3',  '1461-7788-90', 'Land Bank of the Philippines', 'region', 'region')
) AS v(account_name, account_number, bank_name, office_type, department_type)
WHERE NOT EXISTS (SELECT 1 FROM fims.bank_accounts ba WHERE ba.account_number = v.account_number);

INSERT INTO fims.check_booklets (bank_account_id, series, fiscal_year, department_type, start_check_no, end_check_no, date_received)
SELECT ba.bank_account_id, v.series, v.fiscal_year, v.department_type, v.start_check_no, v.end_check_no, v.date_received::date
FROM (VALUES
    ('1461-1234-56', 'A', 2026, 'main',   '0000001', '0000050', '2026-09-01'),
    ('1461-7788-90', 'B', 2026, 'region', '0001001', '0001030', '2026-09-15')
) AS v(account_number, series, fiscal_year, department_type, start_check_no, end_check_no, date_received)
JOIN fims.bank_accounts ba ON ba.account_number = v.account_number
WHERE NOT EXISTS (
    SELECT 1 FROM fims.check_booklets cb
    WHERE cb.bank_account_id = ba.bank_account_id AND cb.series = v.series
);
