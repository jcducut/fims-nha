-- ============================================================================
-- File attachments — the link between a group of files and the record they belong to.
-- One upload batch gets one multiple_node_id (a UUID); each file of the batch is one row
-- here with that multiple_node_id and the file's ip_filemeta.node_id as file_node_id.
-- The owning record stores the multiple_node_id (fims.budget_allocation.multiple_node_id),
-- so "all documents of this memo request" = every row with that multiple_node_id.
-- (FileUploadServices::multiUpload writes it, ::attachmentsFor reads it.)
--
-- node_id is the row's own public id. Soft-delete: deleted_at is stamped instead.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.file_attachments (
    id SERIAL PRIMARY KEY,
    multiple_node_id UUID,
    file_node_id UUID,
    created_by INTEGER,
    node_id UUID NOT NULL DEFAULT gen_random_uuid(),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    CONSTRAINT uq_file_attachments_node_id UNIQUE (node_id)
);

COMMENT ON TABLE  fims.file_attachments IS 'Links an upload batch (multiple_node_id) to each of its files (fims.ip_filemeta.node_id)';
COMMENT ON COLUMN fims.file_attachments.multiple_node_id IS 'Shared by every file of one upload batch; stored on the owning record (e.g. fims.budget_allocation.multiple_node_id)';
COMMENT ON COLUMN fims.file_attachments.file_node_id IS 'fims.ip_filemeta.node_id of the attached file';
COMMENT ON COLUMN fims.file_attachments.created_by IS 'uac.users.user_id of the uploader (plain integer, no cross-schema FK)';
COMMENT ON COLUMN fims.file_attachments.deleted_at IS 'Soft delete: NULL = active, timestamp = deleted';

CREATE INDEX IF NOT EXISTS idx_fims_file_attachments_multiple_node_id ON fims.file_attachments (multiple_node_id);
CREATE INDEX IF NOT EXISTS idx_fims_file_attachments_file_node_id     ON fims.file_attachments (file_node_id);
CREATE INDEX IF NOT EXISTS idx_fims_file_attachments_deleted_at       ON fims.file_attachments (deleted_at);
