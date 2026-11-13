-- ============================================================================
-- Drops the legacy single-RFA snapshot columns on fims.disbursement_vouchers.
-- They were kept as a backward-compatible "first selected RFA" snapshot after
-- "03 - alter_table/23 - budget_allocation_add_dv_id.sql" moved the real link
-- onto the RFA side (fims.budget_allocation.dv_id — see
-- DisbursementVoucherService::linkRfas()); nothing reads them anymore
-- (DisbursementVoucherService::priorPaymentsForRfa() now looks up
-- budget_allocation.dv_id directly, and search/display read dv.rfas, the
-- reverse lookup, instead).
-- ============================================================================
ALTER TABLE fims.disbursement_vouchers
DROP COLUMN IF EXISTS rfa_allocation_id,
DROP COLUMN IF EXISTS rfa_allocation_number;
