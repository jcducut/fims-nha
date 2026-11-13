-- ============================================================================
-- Journal Entry Vouchers (JEV) — the accounting entry Accounting prepares while
-- processing a disbursement voucher (FOR_ACCOUNTING stage; see
-- DisbursementVoucherService::saveJournalEntry()). One JEV per DV (dv_id is
-- UNIQUE) — this project doesn't model a separate obligation-setup JEV and a
-- payment JEV, just the single combined entry Accounting records before
-- forwarding to Treasury. total_debit/total_credit are snapshotted from the
-- lines at save time and must be equal (enforced in the service, not the DB,
-- same reasoning as fims.purchase_orders.total_amount being a snapshot rather
-- than a generated column).
-- ============================================================================
CREATE TABLE IF NOT EXISTS fims.journal_entry_vouchers (
    jev_id       SERIAL PRIMARY KEY,
    jev_no       VARCHAR(30) UNIQUE,
    jev_date     DATE NOT NULL DEFAULT CURRENT_DATE,
    dv_id        INTEGER NOT NULL UNIQUE REFERENCES fims.disbursement_vouchers(dv_id) ON DELETE CASCADE,
    fiscal_yr    SMALLINT,
    particulars  TEXT,
    total_debit  NUMERIC(18,2) NOT NULL DEFAULT 0,
    total_credit NUMERIC(18,2) NOT NULL DEFAULT 0,
    created_by   INTEGER,
    updated_by   INTEGER,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE fims.journal_entry_vouchers IS 'One accounting entry per disbursement voucher, prepared by Accounting during the FOR_ACCOUNTING stage';

CREATE INDEX IF NOT EXISTS idx_journal_entry_vouchers_fiscal_yr ON fims.journal_entry_vouchers (fiscal_yr);
