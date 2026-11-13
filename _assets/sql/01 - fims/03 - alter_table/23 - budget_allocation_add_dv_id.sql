-- ============================================================================
-- RFA (fims.budget_allocation WHERE allocation_type = 'RFA') -> Disbursement
-- Voucher link, put on the RFA side instead of a list/array on the voucher:
-- several budget_allocation rows can carry the same dv_id (one DV drawing
-- from more than one RFA), while each RFA row only ever points at ONE DV at a
-- time — no array/JSONB column anywhere, no new table.
--
-- Soft reference, same reasoning as disbursement_vouchers.rfa_allocation_id
-- (see "03 - alter_table/11 - disbursement_vouchers_add_fk.sql"'s own
-- comment): fims.budget_allocation has no CRUD backend of its own yet, so a
-- hard FK here would block reshaping it later. DisbursementVoucherService
-- enforces the "one DV at a time" rule itself (see fetchRfaOptions() /
-- prepare()'s exclusivity check), not the database.
-- ============================================================================
ALTER TABLE fims.budget_allocation
ADD COLUMN IF NOT EXISTS dv_id INTEGER;

COMMENT ON COLUMN fims.budget_allocation.dv_id IS 'fims.disbursement_vouchers.dv_id this RFA is currently drawn into, if any (soft reference, no FK — see DisbursementVoucherService). NULL = not yet linked to any voucher.';

CREATE INDEX IF NOT EXISTS idx_budget_allocation_dv_id ON fims.budget_allocation (dv_id);
