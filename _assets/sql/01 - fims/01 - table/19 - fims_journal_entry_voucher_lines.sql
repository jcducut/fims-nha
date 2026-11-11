-- Debit/credit lines of a Journal Entry Voucher (see "18 - fims_journal_entry_vouchers.sql").
-- Exactly one of debit/credit is non-zero per line (enforced in
-- DisbursementVoucherService::saveJournalEntry(), not a CHECK constraint, same
-- validate-in-PHP convention the rest of this schema uses). account_code must
-- reference a posting_allowed account (also service-enforced, matching how
-- PurchaseOrderService validates fims.purchase_order_items.account_code).
CREATE TABLE IF NOT EXISTS fims.journal_entry_voucher_lines (
    jevl_id      SERIAL PRIMARY KEY,
    jev_id       INTEGER NOT NULL REFERENCES fims.journal_entry_vouchers(jev_id) ON DELETE CASCADE,
    line_number  INTEGER NOT NULL,
    account_code VARCHAR(30) NOT NULL REFERENCES fims.chart_of_accounts(account_code),
    debit        NUMERIC(18,2) NOT NULL DEFAULT 0,
    credit       NUMERIC(18,2) NOT NULL DEFAULT 0,
    description  VARCHAR(255),
    UNIQUE (jev_id, line_number)
);

CREATE INDEX IF NOT EXISTS idx_journal_entry_voucher_lines_jev_id       ON fims.journal_entry_voucher_lines (jev_id);
CREATE INDEX IF NOT EXISTS idx_journal_entry_voucher_lines_account_code ON fims.journal_entry_voucher_lines (account_code);
