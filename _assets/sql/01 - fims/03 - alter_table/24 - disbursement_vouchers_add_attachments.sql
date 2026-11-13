-- ============================================================================
-- Disbursement Vouchers: Supporting Documents, same attachment convention as
-- fims.budget_allocation — see "01 - table/18 - fims_file_attachments.sql"
-- and FileUploadServices::attachmentsFor(). One column, no new table.
-- ============================================================================
ALTER TABLE fims.disbursement_vouchers
ADD COLUMN IF NOT EXISTS multiple_node_id UUID;

COMMENT ON COLUMN fims.disbursement_vouchers.multiple_node_id IS 'Supporting Documents batch (fims.file_attachments.multiple_node_id) attached to this voucher, if any';

CREATE INDEX IF NOT EXISTS idx_disbursement_vouchers_multiple_node_id ON fims.disbursement_vouchers (multiple_node_id);
