-- Subsidiary Ledger (SL) registry — this project had no SL infrastructure at all until now.
-- Mirrors the General Form No. 1's "SL Code" column on its Accounting Entries grid (see
-- "19 - fims_journal_entry_voucher_lines.sql", given sl_code/sl_detail_id in
-- "03 - alter_table/17 - disbursement_vouchers_drop_tax_fields_add_sl.sql"): a control account in
-- the chart of accounts (account_code) can be broken down into several SL masters, each further
-- broken into sl_details (see "24 - fims_sl_details.sql") — e.g. one Accounts Payable control
-- account, with one SL master per major vendor.
CREATE TABLE IF NOT EXISTS fims.sl_masters (
    sl_code      VARCHAR(30) PRIMARY KEY,
    sl_name      VARCHAR(255) NOT NULL,
    account_code VARCHAR(30) REFERENCES fims.chart_of_accounts(account_code),
    is_active    BOOLEAN NOT NULL DEFAULT TRUE
);

COMMENT ON TABLE  fims.sl_masters IS 'Subsidiary Ledger registry — breaks a chart-of-accounts control account down into named sub-ledgers (e.g. one per major vendor)';
COMMENT ON COLUMN fims.sl_masters.account_code IS 'The GL control account this SL breaks down; nullable since not every SL master is tied to one specific account';

-- account_code is linked later in "03 - alter_table/20 - sl_masters_set_account_code.sql": this
-- file runs before fims.chart_of_accounts is seeded, so setting it here would violate the FK.
INSERT INTO fims.sl_masters (sl_code, sl_name) VALUES
    ('SL-AP-DVDEMO01',   'Accounts Payable — Jackson Auto Services'),
    ('SL-AP-GENERAL',    'Accounts Payable — Vendors (General)'),
    ('SL-CASH-MDS01',    'Cash in Bank — MDS Checking Account'),
    ('SL-DUEBIR-WTAX',   'Due to BIR — Withholding Tax'),
    ('SL-RETENTION-01',  'Guaranty/Retention Payable — Contractors')
ON CONFLICT (sl_code) DO UPDATE SET
    sl_name = EXCLUDED.sl_name;

CREATE INDEX IF NOT EXISTS idx_sl_masters_account_code ON fims.sl_masters (account_code);
