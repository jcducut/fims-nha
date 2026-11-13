-- Property Records: ONE table, one row per PO.
--   fims.property_records   po_number (unique), the PO's responsibility center (ONE per PO, rc_id) and line_transaction (JSONB):
--                           the items the user picked from the Property Inventory System (PIS) for that PO.
-- Items are searched in PIS by PO number (page: app/pages/property-record-page.php) and only the SELECTED items are saved here.
-- Only a PO that is already in the IAR module (fims.inspection_acceptance_report.po_number) can be searched / saved: checked by the app, not by a DB constraint.
-- Nothing else about the PO is copied here (its date, its IAR … stay in the IAR module).
--
-- line_transaction is an ARRAY with one element per saved item. Only these PIS fields are kept, under the PIS's own names:
--   [ { "inv_id": 51422, "TYPENO": "2", "GL": "1-06-05-030", "accnt_desc": "INFORMATION AND COMMUNICATION TECHNOLOGY EQUIPMENT",
--       "GLCODE_SUB": 422, "inv_glcode_sub": "FLASH DRIVE", "PROPNO": "FLASHD32-2024-07-(1-4)", "SNO": "N/A",
--       "ITEMDESC": "FLASH DRIVE, 32GB", "MODELNO": "N/A", "DATEACQD": "2024-07-02", "COST": 395, "UNIT": "1", "SERV": "1",
--       "RCCODE": "35-074-0000010-001", "dept_proj_desc": "Region 10 - Bukidnon/Misamis Occidental/Lanao Del Norte District Office",
--       "PO_NO": "RX-2406044", "DEPTCD": "BML",
--       "District": "Bukidnon | Misamis Occidental | Lanao del Norte District Office" } ]
--   inv_id is unique across ALL rows (an item is saved once); checked by the app. Only items whose PIS status (SERV) is 1-4 can be saved:
--   1 Issued, 2 Returned: Unserviceable, 3 Returned: Serviceable, 4 Donation.
--
-- rc_id = the PO's ONE responsibility center = fims.responsibility_code.rc_id (e.g. 'R10A'), found by the app in this order:
--   1) the PIS RCCODE = responsibility_code.rc_code
--   2) the PIS department / district text = responsibility_code.rc_name
--   3) the responsibility center of the PO in the IAR module (budget allocation RFA of that PO)
-- (NULL = none matched).
--
-- If you created an earlier version of this table locally (rc_matched_by / po_date / rc_code columns), drop it first so this script recreates it:
--   DROP TABLE IF EXISTS fims.property_records CASCADE;

CREATE TABLE IF NOT EXISTS fims.property_records (
    property_record_id SERIAL PRIMARY KEY,
    po_number          VARCHAR(100) NOT NULL,                 -- the PIS PO number (no "PO-" prefix)
    rc_id              VARCHAR(50),                           -- FK -> fims.responsibility_code.rc_id: the PO's one responsibility center
    line_transaction   JSONB        NOT NULL DEFAULT '[]'::jsonb,   -- the saved items, see above
    created_by         INTEGER,
    created_at         TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by         INTEGER,
    updated_at         TIMESTAMPTZ,
    CONSTRAINT uq_property_records_po_number UNIQUE (po_number),
    CONSTRAINT fk_property_records_rc FOREIGN KEY (rc_id) REFERENCES fims.responsibility_code (rc_id),
    CONSTRAINT chk_property_records_lines_array CHECK (jsonb_typeof(line_transaction) = 'array')
);
COMMENT ON TABLE  fims.property_records IS 'Property records per PO number: the PO''s responsibility center (rc_id) and the items picked from the Property Inventory System (line_transaction, JSONB)';
COMMENT ON COLUMN fims.property_records.rc_id IS 'The PO''s one responsibility center (fims.responsibility_code.rc_id): the PIS RC code, else the PIS department/district name, else the RC of the PO in the IAR module';
COMMENT ON COLUMN fims.property_records.line_transaction IS 'Array of saved PIS items (PIS field names: inv_id, TYPENO, GL, accnt_desc, GLCODE_SUB, inv_glcode_sub, PROPNO, SNO, ITEMDESC, MODELNO, DATEACQD, COST, UNIT, SERV, RCCODE, dept_proj_desc, PO_NO, DEPTCD, District); inv_id unique across all rows';

CREATE INDEX IF NOT EXISTS idx_property_records_rc_id ON fims.property_records (rc_id);
CREATE INDEX IF NOT EXISTS idx_property_records_line_transaction ON fims.property_records USING GIN (line_transaction);
