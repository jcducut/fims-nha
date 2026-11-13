-- ============================================================================
-- fims.checks.printed_at — ported from TREASURY's print-agent feature
-- (CheckPreparationService::markPrinted()). Set once a check has actually
-- been sent to a physical printer via the local print agent; purely
-- informational (drives a "Printed" badge + reprint-confirmation prompt),
-- no status transition happens from this column.
-- ============================================================================

ALTER TABLE fims.checks
ADD COLUMN IF NOT EXISTS printed_at TIMESTAMP;

COMMENT ON COLUMN fims.checks.printed_at IS 'Set when the check is sent to a physical printer via the local print agent (see /print-agent) — informational only, not a status transition';
