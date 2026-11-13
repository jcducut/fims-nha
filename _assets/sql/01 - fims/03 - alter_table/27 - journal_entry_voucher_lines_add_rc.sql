-- ============================================================================
-- Adds the "Resp Center" column to fims.journal_entry_voucher_lines, completing
-- the standard NGAS Journal Entry Voucher grid layout:
--   Resp Center | Accounts and Explanation | SL Code | Subsidiary Ledger (Dr/Cr)
--   | GL Acct Code | General Ledger (Dr/Cr)
-- "Accounts and Explanation", "GL Acct Code" and "General Ledger" aren't new
-- columns — they're account_code/description (GL Acct Code + the GL header's
-- own title) and the GL-level debit/credit totals, computed by summing a
-- group's own lines (see readone.php's vje_groupedEntriesCard() /
-- vje_entriesCard()'s grouped grid). Same nullable, service-validated FK
-- convention as sl_code/sl_detail_id (see "03 - alter_table/17 -
-- disbursement_vouchers_drop_tax_fields_add_sl.sql") — not every line needs a
-- Responsibility Center.
-- ============================================================================
ALTER TABLE fims.journal_entry_voucher_lines
ADD COLUMN IF NOT EXISTS rc_id VARCHAR(10);

COMMENT ON COLUMN fims.journal_entry_voucher_lines.rc_id IS 'Responsibility Center (fims.responsibility_code) this line is charged against, if any — the paper JEV''s "Resp Center" column';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_journal_entry_voucher_lines_rc_id'
    ) THEN
        ALTER TABLE fims.journal_entry_voucher_lines
        ADD CONSTRAINT fk_journal_entry_voucher_lines_rc_id
        FOREIGN KEY (rc_id) REFERENCES fims.responsibility_code(rc_id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_journal_entry_voucher_lines_rc_id ON fims.journal_entry_voucher_lines (rc_id);
