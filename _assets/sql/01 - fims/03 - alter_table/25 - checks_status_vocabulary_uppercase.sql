-- ============================================================================
-- fims.checks.status — aligns the default/vocabulary to TREASURY's actual
-- check-preparation.php/CheckPreparationService.php casing (PREPARED/
-- RELEASED/ENCASHED/OUTSTANDING/STALE/CANCELLED/VOIDED, all uppercase) —
-- the original migration ("01 - table/28") used lowercase
-- ('prepared'|'released'|...) as a guess before this project's Check
-- Preparation tab was checked against the real TREASURY source.
-- saveCheck() now inserts 'PREPARED' explicitly rather than relying on the
-- column default, but the default is updated too so any other future
-- writer stays consistent. Existing rows are uppercased for the same
-- reason (idempotent — a second run is a no-op).
-- ============================================================================

UPDATE fims.checks SET status = UPPER(status) WHERE status <> UPPER(status);

ALTER TABLE fims.checks ALTER COLUMN status SET DEFAULT 'PREPARED';

COMMENT ON COLUMN fims.checks.status IS 'PREPARED -> RELEASED -> ENCASHED | OUTSTANDING -> STALE; or -> CANCELLED/VOIDED from PREPARED/RELEASED (matches TREASURY''s check-preparation vocabulary exactly)';
