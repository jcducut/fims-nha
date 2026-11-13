-- =============================================================================
-- UAC: Split the document header into managed parts (slots)
-- -----------------------------------------------------------------------------
-- The NHA letterhead is not one picture — it is four pieces laid out in a row:
--
--   [ NHA logo ]  [ NATIONAL HOUSING AUTHORITY  ]  [ Pambansang ]  [ Bagong   ]
--                 [ Build Better and More Housing]  [ Pabahay    ]  [ Pilipinas]
--     LOGO                    TITLE                    BRAND           SEAL
--    (image)                  (text)                  (image)         (image)
--
-- Managing it as a single 1250x188 PNG means re-cutting the whole banner to
-- swap one seal or fix a tagline. This migration lets each part be uploaded,
-- replaced and positioned on its own, and lets the TITLE part be real text
-- rather than a picture of text (which also keeps it selectable/searchable in
-- the generated DOCX).
--
-- The generators compose the four parts into a 4-cell borderless table sized to
-- the same 630x95 px footprint the single image used, so nothing downstream
-- changes. A whole-header image (slot NULL) is still honoured and still wins,
-- so existing setups keep working untouched.
--
-- Strategy: additive, IF NOT EXISTS throughout — safe to re-run.
-- =============================================================================

SET search_path TO uac, public;

-- =============================================================================
-- SEQUENCE
-- =============================================================================
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'uac' AND sequencename = 'document_header_slots_header_slot_id_seq') THEN
        CREATE SEQUENCE uac.document_header_slots_header_slot_id_seq
            INCREMENT 1 MINVALUE 1 MAXVALUE 2147483647 START 1 CACHE 1;
    END IF;
END $$;

-- =============================================================================
-- TYPE TABLE: the four parts, their order and their default geometry
-- =============================================================================
CREATE TABLE IF NOT EXISTS uac.document_header_slots (
    header_slot_id     INT4         NOT NULL DEFAULT nextval('uac.document_header_slots_header_slot_id_seq'),
    slot_code          VARCHAR(20)  NOT NULL,          -- LOGO | TITLE | BRAND | SEAL
    slot_name          VARCHAR(100) NOT NULL,
    part_kind          VARCHAR(10)  NOT NULL,          -- image | text
    display_order      INT4         NOT NULL,          -- left to right across the letterhead
    -- Share of the header width this column occupies. The four active slots
    -- should total 100; the generator normalises whatever it finds.
    default_column_pct NUMERIC(5,2) NOT NULL,
    -- Default size the image inside the column is drawn at (NULL for text).
    default_render_width  INT4,
    default_render_height INT4,
    default_align      VARCHAR(10)  NOT NULL DEFAULT 'center',  -- left | center | right
    is_active          BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT document_header_slots_pkey    PRIMARY KEY (header_slot_id),
    CONSTRAINT document_header_slots_code_uk UNIQUE (slot_code)
);
COMMENT ON TABLE uac.document_header_slots IS
    'The four parts of the NHA letterhead (LOGO, TITLE, BRAND, SEAL), their left-to-right order and default column geometry.';

-- =============================================================================
-- EXTEND uac.document_header_assets to carry a part
-- =============================================================================
ALTER TABLE uac.document_header_assets
    ADD COLUMN IF NOT EXISTS header_slot_id INT4,                       -- NULL = whole-header image (legacy behaviour)
    ADD COLUMN IF NOT EXISTS part_kind      VARCHAR(10) NOT NULL DEFAULT 'image',
    ADD COLUMN IF NOT EXISTS column_pct     NUMERIC(5,2),               -- overrides the slot default
    ADD COLUMN IF NOT EXISTS align          VARCHAR(10),                -- overrides the slot default
    -- Text parts (TITLE). Kept as plain columns rather than JSONB because they
    -- are fixed, searchable attributes, not dynamic config.
    ADD COLUMN IF NOT EXISTS text_line1     TEXT,
    ADD COLUMN IF NOT EXISTS text_line2     TEXT,
    ADD COLUMN IF NOT EXISTS font_name      VARCHAR(60),
    ADD COLUMN IF NOT EXISTS line1_size_pt  NUMERIC(4,1),
    ADD COLUMN IF NOT EXISTS line2_size_pt  NUMERIC(4,1),
    ADD COLUMN IF NOT EXISTS line1_bold     BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS line2_bold     BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS line1_color    VARCHAR(8),                 -- RRGGBB, no leading #
    ADD COLUMN IF NOT EXISTS line2_color    VARCHAR(8);

COMMENT ON COLUMN uac.document_header_assets.header_slot_id IS
    'Which part of the letterhead this asset fills. NULL means it is a whole-header image, which overrides the composed parts.';
COMMENT ON COLUMN uac.document_header_assets.part_kind IS
    'image = the row carries image_data; text = the row carries text_line1/text_line2 and no image.';
COMMENT ON COLUMN uac.document_header_assets.column_pct IS
    'Share of the header width this part occupies. NULL falls back to the slot default.';

-- A text part has no upload, so the payload can no longer be mandatory.
ALTER TABLE uac.document_header_assets ALTER COLUMN image_data  DROP NOT NULL;
ALTER TABLE uac.document_header_assets ALTER COLUMN mime_type   DROP NOT NULL;
ALTER TABLE uac.document_header_assets ALTER COLUMN file_name   DROP NOT NULL;
ALTER TABLE uac.document_header_assets ALTER COLUMN file_size   DROP NOT NULL;

-- Whatever the kind, the row must actually carry the content it claims to.
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'document_header_assets_payload_ck') THEN
        ALTER TABLE uac.document_header_assets
            ADD CONSTRAINT document_header_assets_payload_ck CHECK (
                (part_kind = 'image' AND image_data IS NOT NULL)
             OR (part_kind = 'text'  AND COALESCE(text_line1, text_line2) IS NOT NULL)
            );
    END IF;
END $$;

DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'document_header_assets_slot_fk') THEN
        ALTER TABLE uac.document_header_assets
            ADD CONSTRAINT document_header_assets_slot_fk FOREIGN KEY (header_slot_id)
                REFERENCES uac.document_header_slots(header_slot_id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_dha_slot ON uac.document_header_assets(header_slot_id);

-- =============================================================================
-- Re-scope "only one active per scope" to include the slot
-- -----------------------------------------------------------------------------
-- Previously one HEADER could be active per (type, scope). Now each PART may be
-- active per (type, slot, scope) — four active header rows is the normal state.
-- =============================================================================
DROP INDEX IF EXISTS uac.uq_dha_active_per_scope;

CREATE UNIQUE INDEX IF NOT EXISTS uq_dha_active_per_slot_scope
    ON uac.document_header_assets (
        document_asset_type_id,
        COALESCE(header_slot_id, 0),
        COALESCE(scope_system_code,   '*'),
        COALESCE(scope_document_code, '*')
    )
    WHERE is_active = TRUE;

-- =============================================================================
-- SEED: the four parts, sized to reproduce today's 630x95 letterhead
-- -----------------------------------------------------------------------------
-- Percentages and sizes are derived from the existing 1250x188 banner scaled to
-- the 630 px render width. They are defaults only — each is editable per asset.
-- =============================================================================
INSERT INTO uac.document_header_slots
    (slot_code, slot_name, part_kind, display_order, default_column_pct,
     default_render_width, default_render_height, default_align)
VALUES
    ('LOGO',  'NHA Logo',                          'image', 1, 14.00,  57, 48, 'center'),
    ('TITLE', 'Agency Name & Tagline',             'text',  2, 47.00, NULL, NULL, 'center'),
    ('BRAND', 'Pambansang Pabahay para sa Pilipino','image', 3, 20.00, 100, 50, 'center'),
    ('SEAL',  'Bagong Pilipinas',                  'image', 4, 19.00,  68, 63, 'center')
ON CONFLICT (slot_code) DO UPDATE
    SET slot_name             = EXCLUDED.slot_name,
        part_kind             = EXCLUDED.part_kind,
        display_order         = EXCLUDED.display_order,
        default_column_pct    = EXCLUDED.default_column_pct,
        default_render_width  = EXCLUDED.default_render_width,
        default_render_height = EXCLUDED.default_render_height,
        default_align         = EXCLUDED.default_align;

SELECT setval(
    'uac.document_header_slots_header_slot_id_seq',
    COALESCE((SELECT MAX(header_slot_id) FROM uac.document_header_slots), 0) + 1,
    false
);

-- =============================================================================
-- SEED: the TITLE text, so the wording is managed from day one
-- -----------------------------------------------------------------------------
-- Only inserted when no TITLE part exists yet; an edited title is never
-- overwritten by a re-run.
-- =============================================================================
INSERT INTO uac.document_header_assets (
    asset_code, asset_name, document_asset_type_id, header_slot_id, part_kind,
    render_width, render_height,
    text_line1, text_line2, font_name,
    line1_size_pt, line2_size_pt, line1_bold, line2_bold, line1_color, line2_color,
    is_active, notes
)
SELECT
    'HDR-TITLE', 'NHA Agency Name & Tagline', t.document_asset_type_id, s.header_slot_id, 'text',
    0, 0,
    'NATIONAL HOUSING AUTHORITY', 'Build Better and More Housing', 'Arial',
    16.0, 9.0, TRUE, FALSE, '000000', '000000',
    TRUE, 'Seeded from the letterhead artwork. Edit the wording in UAC > Header Management.'
FROM uac.document_asset_types t
CROSS JOIN uac.document_header_slots s
WHERE t.type_code = 'HEADER'
  AND s.slot_code = 'TITLE'
  AND NOT EXISTS (
        SELECT 1 FROM uac.document_header_assets a
         WHERE a.header_slot_id = s.header_slot_id
           AND a.scope_system_code IS NULL
           AND a.scope_document_code IS NULL
  );

SELECT setval(
    'uac.document_header_assets_header_asset_id_seq',
    COALESCE((SELECT MAX(header_asset_id) FROM uac.document_header_assets), 0) + 1,
    false
);
