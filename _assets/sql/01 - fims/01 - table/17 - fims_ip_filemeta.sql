-- ============================================================================
-- File metadata — one row per uploaded file or typed link / reference.
-- Used by the Supporting Documents section of the CBA / Memo Request form
-- (FileUploadServices, app/routes/file_upload.php). The file itself lives in object
-- storage (MinIO / S3); "link" holds its object key — or, when the user typed a link
-- instead of uploading, that link / reference.
--
-- node_id is the row's public id: fims.file_attachments.file_node_id points at it.
-- Soft-delete: rows are never removed, deleted_at is stamped instead.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.ip_filemeta (
    id SERIAL PRIMARY KEY,
    document_name VARCHAR(225),
    link TEXT,
    created_by INTEGER,
    node_id UUID NOT NULL DEFAULT gen_random_uuid(),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    CONSTRAINT uq_ip_filemeta_node_id UNIQUE (node_id)
);

COMMENT ON TABLE  fims.ip_filemeta IS 'Metadata of an uploaded file (or a typed link / reference): its name and storage key / link';
COMMENT ON COLUMN fims.ip_filemeta.link IS 'Object-storage key of an uploaded file, or the link / reference the user typed';
COMMENT ON COLUMN fims.ip_filemeta.created_by IS 'uac.users.user_id of the uploader (plain integer, no cross-schema FK)';
COMMENT ON COLUMN fims.ip_filemeta.node_id IS 'Public id of this file; referenced by fims.file_attachments.file_node_id';
COMMENT ON COLUMN fims.ip_filemeta.deleted_at IS 'Soft delete: NULL = active, timestamp = deleted';

CREATE INDEX IF NOT EXISTS idx_fims_ip_filemeta_deleted_at ON fims.ip_filemeta (deleted_at);
