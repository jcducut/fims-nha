-- PHPMaker generated a full CRUD-less scaffold for this view (AggregatedAuditLogsController/
-- Grid/List/View, src/Entity/AggregatedAuditLog.php) and app/pages/main-dashboard.php queries
-- it directly, but the view itself was never created - causing:
--   SQLSTATE[42P01]: Undefined table: relation "aggregated_audit_logs" does not exist
--
-- Bundles the per-field rows uac.audit_logs writes for a single save (one row per changed
-- field) into one row per "action" (same script/user/action/table/key_value within the same
-- minute), matching the columns src/Entity/AggregatedAuditLog.php expects:
--   aggregated_id (int), action_date (text), script, "user", action, "table",
--   action_type (text), details (text), action_count (bigint)
CREATE OR REPLACE VIEW uac.aggregated_audit_logs AS
SELECT
    MAX(id) AS aggregated_id,
    TO_CHAR(MIN(date_time), 'YYYY-MM-DD') AS action_date,
    script,
    "user",
    action,
    "table",
    INITCAP(action) AS action_type,
    STRING_AGG(
        COALESCE(field, '(n/a)') || ': ' || COALESCE(old_value, '(none)') || ' -> ' || COALESCE(new_value, '(none)'),
        E'\n' ORDER BY id
    ) AS details,
    COUNT(*) AS action_count
FROM uac.audit_logs
GROUP BY script, "user", action, "table", key_value, date_trunc('minute', date_time);
