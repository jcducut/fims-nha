-- =============================================================================
-- UAC: Document Header / Footer Asset Registry
-- Purpose: Single source of truth for the letterhead (header) and footer images
--          stamped onto every generated DOCX across FIMS.
--
-- Before this table the images were hard-coded as base64 string literals inside
-- REGIONAL/scripts/gen_cba_docx.js, and every other generator either duplicated
-- that literal (gen_caf_docx.js) or scraped it back out of gen_cba_docx.js at
-- runtime with a regex (gen_pr_docx.js, gen_po_docx.js, gen_crf_docx.js,
-- gen_ftla_docx.js, gen_laa_docx.js). Changing the letterhead meant editing
-- source files.
--
-- Consumers today:
--   /UAC/HeaderManagement            — upload / activate / preview (CRUD UI)
--   /UAC/api/doc-headers/*           — REST surface for the page
--   _shared/branding/                — the ACTIVE asset is mirrored to disk so
--                                      the Node generators need no DB round-trip
--   REGIONAL/scripts/lib/branding.js — resolver used by all 7 DOCX generators
--
-- Render size is stored per asset because the generators hand it straight to
-- docx's ImageRun transformation. Established NHA sizes:
--     header -> 630 x 95 px
--     footer -> 415 x 35 px
--
-- Strategy: CREATE IF NOT EXISTS — safe migration, no DROP
-- =============================================================================

SET search_path TO uac, public;

-- =============================================================================
-- SEQUENCES
-- =============================================================================
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'uac' AND sequencename = 'document_asset_types_document_asset_type_id_seq') THEN
        CREATE SEQUENCE uac.document_asset_types_document_asset_type_id_seq
            INCREMENT 1 MINVALUE 1 MAXVALUE 2147483647 START 1 CACHE 1;
    END IF;
END $$;

DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'uac' AND sequencename = 'document_header_assets_header_asset_id_seq') THEN
        CREATE SEQUENCE uac.document_header_assets_header_asset_id_seq
            INCREMENT 1 MINVALUE 1 MAXVALUE 2147483647 START 1 CACHE 1;
    END IF;
END $$;

-- =============================================================================
-- TABLE: uac.document_asset_types  (type table — no enums, no CHECK lists)
-- =============================================================================
CREATE TABLE IF NOT EXISTS uac.document_asset_types (
    document_asset_type_id INT4         NOT NULL DEFAULT nextval('uac.document_asset_types_document_asset_type_id_seq'),
    category               VARCHAR(50)  NOT NULL,                       -- grouping, e.g. 'document_branding'
    type_code              VARCHAR(20)  NOT NULL,                       -- HEADER | FOOTER
    type_name              VARCHAR(100) NOT NULL,                       -- human label for the UI
    default_render_width   INT4         NOT NULL,                       -- px handed to docx ImageRun
    default_render_height  INT4         NOT NULL,
    is_active              BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT document_asset_types_pkey    PRIMARY KEY (document_asset_type_id),
    CONSTRAINT document_asset_types_code_uk UNIQUE (category, type_code)
);
COMMENT ON TABLE uac.document_asset_types IS
    'Kinds of document branding asset (HEADER, FOOTER) and the pixel size each is rendered at in generated DOCX files.';

-- =============================================================================
-- TABLE: uac.document_header_assets
-- =============================================================================
CREATE TABLE IF NOT EXISTS uac.document_header_assets (
    header_asset_id        INT4         NOT NULL DEFAULT nextval('uac.document_header_assets_header_asset_id_seq'),
    asset_code             VARCHAR(40)  NOT NULL,                       -- HDR-0001 / FTR-0001, business reference
    asset_name             VARCHAR(150) NOT NULL,
    document_asset_type_id INT4         NOT NULL,

    -- Scope. NULL means "applies everywhere"; a value narrows the asset down.
    -- Resolution picks the most specific match:
    --   system + document  >  document only  >  system only  >  global
    scope_system_code      VARCHAR(20),                                 -- REGIONAL, ACCOUNTING, TREASURY, ...
    scope_document_code    VARCHAR(30),                                 -- CBA, RFA, LAA, PR, PO, CAF, CRF, FTLA

    -- Uploaded file
    file_name              VARCHAR(255) NOT NULL,
    mime_type              VARCHAR(60)  NOT NULL,                       -- image/png | image/jpeg
    file_size              INT4         NOT NULL,                       -- bytes, decoded
    checksum_sha256        CHAR(64),                                    -- of the decoded bytes; dedupe + change detection

    -- Intrinsic pixel size of the upload (what the image actually is)
    image_width            INT4,
    image_height           INT4,

    -- Size the generators render it at (what docx ImageRun receives)
    render_width           INT4         NOT NULL,
    render_height          INT4         NOT NULL,

    -- Base64 payload. TEXT rather than BYTEA so it drops straight into the JSON
    -- payload the Node generators read, with no encoding hop in PHP.
    image_data             TEXT         NOT NULL,

    is_active              BOOLEAN      NOT NULL DEFAULT FALSE,
    notes                  TEXT,

    created_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by             INT4,                                        -- uac.users.user_id
    updated_by             INT4,

    CONSTRAINT document_header_assets_pkey    PRIMARY KEY (header_asset_id),
    CONSTRAINT document_header_assets_code_uk UNIQUE (asset_code),
    CONSTRAINT document_header_assets_type_fk FOREIGN KEY (document_asset_type_id)
        REFERENCES uac.document_asset_types(document_asset_type_id)
);
COMMENT ON TABLE  uac.document_header_assets IS
    'Uploaded header/footer images for generated DOCX documents. Exactly one row may be active per (type, scope); the active row is mirrored to _shared/branding/ for the Node generators.';
COMMENT ON COLUMN uac.document_header_assets.image_data          IS 'Base64-encoded image bytes (no data: URI prefix). Decoded straight into a Buffer by the DOCX generators.';
COMMENT ON COLUMN uac.document_header_assets.scope_system_code   IS 'NULL = applies to every system. Otherwise REGIONAL, ACCOUNTING, TREASURY, ...';
COMMENT ON COLUMN uac.document_header_assets.scope_document_code IS 'NULL = applies to every document. Otherwise the form code: CBA, RFA, LAA, PR, PO, CAF, CRF, FTLA.';
COMMENT ON COLUMN uac.document_header_assets.render_width        IS 'Width in px passed to docx ImageRun transformation. 630 for headers, 415 for footers.';
COMMENT ON COLUMN uac.document_header_assets.render_height       IS 'Height in px passed to docx ImageRun transformation. 95 for headers, 35 for footers.';

-- =============================================================================
-- INDEXES
-- =============================================================================
CREATE INDEX IF NOT EXISTS idx_dha_type       ON uac.document_header_assets(document_asset_type_id);
CREATE INDEX IF NOT EXISTS idx_dha_active     ON uac.document_header_assets(is_active);
CREATE INDEX IF NOT EXISTS idx_dha_scope      ON uac.document_header_assets(scope_system_code, scope_document_code);
CREATE INDEX IF NOT EXISTS idx_dha_created_by ON uac.document_header_assets(created_by);
CREATE INDEX IF NOT EXISTS idx_dha_checksum   ON uac.document_header_assets(checksum_sha256);

-- Only one asset may be active per (type, scope). COALESCE is required because
-- NULL scope columns would otherwise never collide in a UNIQUE index.
CREATE UNIQUE INDEX IF NOT EXISTS uq_dha_active_per_scope
    ON uac.document_header_assets (
        document_asset_type_id,
        COALESCE(scope_system_code, '*'),
        COALESCE(scope_document_code, '*')
    )
    WHERE is_active = TRUE;

-- =============================================================================
-- TRIGGER: keep updated_at honest
-- =============================================================================
CREATE OR REPLACE FUNCTION uac.trg_dha_touch_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at := CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS dha_touch_updated_at ON uac.document_header_assets;
CREATE TRIGGER dha_touch_updated_at
    BEFORE UPDATE ON uac.document_header_assets
    FOR EACH ROW
    EXECUTE FUNCTION uac.trg_dha_touch_updated_at();
