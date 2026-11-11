-- ALTER: fims.disbursement_vouchers
-- Supports the paper General Form No. 1's "Payments / This Request / Total Payments" lines in
-- Box B (Accounting Entries) — progress payments where several DVs are drawn against the same
-- RFA/obligation over time (e.g. construction progress billings), not just one. Previously this
-- project enforced one RFA per DV (see DisbursementVoucherService::fetchRfaOptions) and had no
-- "payments prior to this voucher" figure — see "16 - fims_disbursement_vouchers.sql" for that
-- now-outdated comment.
--
-- Snapshotted at save time (DisbursementVoucherService::create()/update()), same convention as
-- the existing budget_amount/obligation_amount columns: it's the sum of net_amount from every
-- other non-CANCELLED/REJECTED voucher already on this RFA as of the moment THIS voucher was
-- saved — not a live-recomputed total, so earlier vouchers don't retroactively change once a
-- later one is added (matches how a signed paper DV isn't edited after the fact either).
-- balance_amount is now obligation_amount - (payments_prior_amount + net_amount), not just
-- obligation_amount - net_amount.
ALTER TABLE fims.disbursement_vouchers
ADD COLUMN IF NOT EXISTS payments_prior_amount NUMERIC(18,2) NOT NULL DEFAULT 0;

COMMENT ON COLUMN fims.disbursement_vouchers.payments_prior_amount IS 'Sum of net_amount from other non-CANCELLED/REJECTED vouchers already on this RFA as of save time (progress payments); 0 when no RFA is linked';
COMMENT ON COLUMN fims.disbursement_vouchers.balance_amount IS 'obligation_amount - (payments_prior_amount + net_amount)';
