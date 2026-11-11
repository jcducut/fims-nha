-- Notification hub (see _shared/NOTIFICATION-IMPLEMENTATION-PLAN.md), schema uac.
-- Upgrades the existing uac.notifications table in place and adds per-user read state:
--   * audience vocabulary enforced by CHECK: all | system | user | userlevel | office | usergroup
--   * sender_id (who triggered it), event_key (de-dupe key; unique when set)
--   * notification_reads join table replaces the legacy CSV read_users / is_read columns
--     (the legacy columns and "timestamp" are left in place, unused, so other apps are not broken).
-- Idempotent. Only safe to tighten NOT NULL / CHECK because the table was empty when this was written;
-- the DO block below refuses to run the tightening if legacy rows with other types exist.

ALTER TABLE uac.notifications
    ADD COLUMN IF NOT EXISTS sender_id int,
    ADD COLUMN IF NOT EXISTS event_key varchar(100);

ALTER TABLE uac.notifications ALTER COLUMN from_system TYPE varchar(30);

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM uac.notifications
               WHERE type IS NULL OR target IS NULL OR subject IS NULL OR from_system IS NULL
                  OR type NOT IN ('all','system','user','userlevel','office','usergroup')) THEN
        RAISE EXCEPTION 'uac.notifications has legacy rows (old types such as personal/userLevel); migrate or delete them first';
    END IF;

    ALTER TABLE uac.notifications
        ALTER COLUMN type SET NOT NULL,
        ALTER COLUMN target SET NOT NULL,
        ALTER COLUMN subject SET NOT NULL,
        ALTER COLUMN from_system SET NOT NULL,
        ALTER COLUMN created_at SET NOT NULL;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_notifications_type') THEN
        ALTER TABLE uac.notifications
            ADD CONSTRAINT ck_notifications_type CHECK (type IN ('all','system','user','userlevel','office','usergroup'));
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS ix_notifications_audience ON uac.notifications (type, target, created_at DESC);
CREATE UNIQUE INDEX IF NOT EXISTS ux_notifications_event ON uac.notifications (event_key) WHERE event_key IS NOT NULL;

CREATE TABLE IF NOT EXISTS uac.notification_reads (
    notification_id varchar(50) NOT NULL REFERENCES uac.notifications(id) ON DELETE CASCADE,
    user_id         int         NOT NULL,
    read_at         timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (notification_id, user_id)
);
CREATE INDEX IF NOT EXISTS ix_notification_reads_user ON uac.notification_reads (user_id);
