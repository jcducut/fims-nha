-- ============================================================================
-- Letterhead layouts — the saved default header (and footer text) for a
-- printed document, one row per document code (CBA, RFA, LAA, CAF, …).
--
-- Written by "Save as default header" in the print preview
-- (LetterheadLayoutService::save, POST /api/letterhead/{doc}).
-- No row = the built-in letterhead. Deleting the row ("Restore original")
-- brings the built-in one back.
--
-- layout (JSONB):
--   { "items": [ { "type": "image", "asset": "logo" | "src": "data:image/png;base64,...",
--                  "height": 57, "width_pct": 14, "align": "center" },
--                { "type": "title", "line1": "...", "line2": "...", "size1": 16, "size2": 9,
--                  "bold1": true, "width_pct": 51, "align": "center" }, ... ],
--     "footer_text": "..." }
-- ============================================================================

-- =============================================
-- LETTERHEAD_LAYOUT TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS fims.letterhead_layout();
ALTER TABLE fims.letterhead_layout
ADD COLUMN IF NOT EXISTS document_code VARCHAR(30) PRIMARY KEY,
ADD COLUMN IF NOT EXISTS layout JSONB NOT NULL,
ADD COLUMN IF NOT EXISTS updated_by INTEGER,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE  fims.letterhead_layout IS 'Saved default letterhead (header items + footer text) per printed document code';
COMMENT ON COLUMN fims.letterhead_layout.document_code IS 'Printed document: CBA, RFA, LAA, ...';
COMMENT ON COLUMN fims.letterhead_layout.layout IS 'Header items left to right + footer_text (see file header)';
COMMENT ON COLUMN fims.letterhead_layout.updated_by IS 'uac.users.user_id of the last saver';
