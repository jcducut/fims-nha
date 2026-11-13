# Reusable Notification Module — Implementation Plan

> A project-agnostic blueprint for adding an in-app **notification bell**
> (stored notifications, per-user read state, polling + optional real-time
> push) to any web project — single app or a suite of apps sharing one login.
>
> It is distilled from the NHA FIMS implementation (see **Appendix A**) and
> its README (`_shared/NOTIFICATION_README.md`), keeping what works and
> fixing what didn't. Nothing in §1–§11 depends on FIMS; replace the
> placeholders below with your own names.

| Placeholder | Meaning | FIMS value (example) |
|---|---|---|
| `{HUB}` | The one app/service that owns the notifications table + API | `UAC` |
| `{HUB_API}` | Base URL of the hub's API | `/UAC/api` |
| `{APP_CODE}` | Code of the app sending/showing a notification | `ACCOUNTING-BAPD` |
| `{TOPIC_PREFIX}` | MQTT topic root | `app` |
| `{DB_SCHEMA}` | Schema/database holding the table | `uac` |

---

## 1. Architecture

```
            ┌──────────────── any app ({APP_CODE}) ────────────────┐
 business   │  service code ──► Notify::toUser()/toRole()/...      │
 event      │                      │ (library or HTTP POST /send)  │
            └──────────────────────┼───────────────────────────────┘
                                   ▼
            ┌─────────────────── {HUB} ────────────────────────────┐
            │ INSERT notifications  ──►  publish {TOPIC_PREFIX}/…  │──► MQTT broker
            │ GET recent / count, POST mark-read / mark-all-read   │       │ (wss)
            └──────────────────────▲───────────────────────────────┘       │
                                   │ fetch (poll 30 s, on focus, on push)  │
            ┌──────────────── browser (every page) ────────────────┐       │
            │ Bell component  ◄── 'app:notification' DOM event ◄───┼── MQTT client
            └──────────────────────────────────────────────────────┘
```

**Rules that make it reusable**

1. **One hub owns the data.** A single table and a single read API serve every
   app. Other apps never query the table directly to *read*.
2. **Senders go through one library or one endpoint**, never ad-hoc INSERTs, so
   the audience vocabulary and topic names can't drift.
3. **Polling is the delivery guarantee; real-time is an optimisation.** If the
   broker is down or unconfigured, notifications still arrive within one poll.
4. **The bell is one shared component** included once in each app's layout.

---

## 2. Design decisions to make up front

| Decision | Options | Recommendation |
|---|---|---|
| Hub vs per-app | one central hub · each app has its own table | **Central hub** if apps share users/login; per-app only for a standalone project (then hub = the app itself). |
| Cross-app auth for the bell | shared session cookie on one domain · token issued by the hub · per-app proxy endpoint | Shared session cookie when all apps are on one domain/path tree (FIMS). Otherwise have each app **proxy** `/notifications/*` to the hub with a service token. |
| Read state | CSV column `read_users` · join table `notification_reads` | **Join table** for new projects (indexable, no string parsing). CSV only to stay compatible with an existing table. |
| Real-time transport | none (poll only) · MQTT (EMQX/Mosquitto) · WebSocket/SSE · Pusher-like SaaS | Start with **poll only**; add real-time behind the same payload contract (§6). |
| Retention | keep forever · purge read after N days | Purge/archive read rows after 90 days. |

---

## 3. Data model

### 3.1 Tables (PostgreSQL; adjust types for MySQL/SQL Server)

```sql
CREATE TABLE {DB_SCHEMA}.notifications (
  id           varchar(50)  PRIMARY KEY,           -- e.g. 'ntf_' || uuid / uniqid
  type         varchar(20)  NOT NULL,              -- audience kind, §3.2
  target       varchar(50)  NOT NULL,              -- audience id as text ('all' for type=all)
  subject      varchar(200) NOT NULL,
  body         text,
  link         varchar(255),                       -- relative URL opened on click
  from_system  varchar(30)  NOT NULL,              -- {APP_CODE} of the sender
  sender_id    int,                                -- user who triggered it (nullable, no FK)
  event_key    varchar(100),                       -- optional dedupe key, e.g. 'jev:123:returned'
  created_at   timestamptz  NOT NULL DEFAULT now(),
  CONSTRAINT ck_notifications_type CHECK (type IN ('all','system','user','userlevel','office','usergroup'))
);
CREATE INDEX ix_notifications_audience ON {DB_SCHEMA}.notifications (type, target, created_at DESC);
CREATE UNIQUE INDEX ux_notifications_event ON {DB_SCHEMA}.notifications (event_key) WHERE event_key IS NOT NULL;

CREATE TABLE {DB_SCHEMA}.notification_reads (
  notification_id varchar(50) REFERENCES {DB_SCHEMA}.notifications(id) ON DELETE CASCADE,
  user_id         int NOT NULL,
  read_at         timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (notification_id, user_id)
);
```

> Compatibility note: if you must reuse an existing table with a CSV
> `read_users` column (FIMS), keep it and implement §4's read logic with
> `user_id = ANY(string_to_array(read_users, ','))`. Everything else in this
> plan stays the same.

### 3.2 Audience types (the one vocabulary)

Use these exact lower-case values in the table, the sender library, the API
and the MQTT topic. Map each to **your** project's membership tables.

| `type` | `target` | Recipients | Resolver — fill in for your project |
|---|---|---|---|
| `all` | `all` | every user | — |
| `system` | app/module id | users with access to that app | e.g. `user_app_access(user_id, app_id)` |
| `user` | user id | one user | `target = :user_id` |
| `userlevel` | role id | users holding that role | e.g. `user_roles(user_id, role_id)` |
| `office` | office/department id | users in that unit | e.g. `user_offices(user_id, office_id)` |
| `usergroup` | supervisor user id | direct reports of that user | e.g. `users.reports_to_user_id` |

Drop types you don't need; never add synonyms (`personal`, `role`, `userLevel` …).

### 3.3 Audience query (the heart of the read side)

```sql
-- :uid = current user
SELECT n.*, (r.user_id IS NOT NULL) AS read_by_user
FROM {DB_SCHEMA}.notifications n
LEFT JOIN {DB_SCHEMA}.notification_reads r ON r.notification_id = n.id AND r.user_id = :uid
WHERE (n.type = 'all'       AND n.target = 'all')
   OR (n.type = 'user'      AND n.target = :uid::text)
   OR (n.type = 'userlevel' AND n.target IN (SELECT role_id::text   FROM user_roles       WHERE user_id = :uid))
   OR (n.type = 'system'    AND n.target IN (SELECT app_id::text    FROM user_app_access  WHERE user_id = :uid))
   OR (n.type = 'office'    AND n.target IN (SELECT office_id::text FROM user_offices     WHERE user_id = :uid))
   OR (n.type = 'usergroup' AND n.target = (SELECT reports_to_user_id::text FROM users    WHERE user_id = :uid))
ORDER BY read_by_user ASC, n.created_at DESC
LIMIT :limit;
```

Put this in **one** function (`getUserNotifications($uid, $limit)`) and reuse it
for `recent`, `count`, `mark-read` (authorisation check) and `mark-all-read`.

---

## 4. Hub API contract

All endpoints authenticated (§2 decision). Base: `{HUB_API}`.

| Method & path | Request | Response | Notes |
|---|---|---|---|
| `GET /notifications/recent?limit=` | — | `{success, data:[{id,type,target,subject,body,link,from_system,created_at,time_ago,read_by_user}], unread_count}` | Default 10, clamp 1–200. Unread first, newest first. |
| `GET /notifications/count` | — | `{success, unread_count}` | For badges that don't need the list. |
| `POST /notifications/mark-read` | `{id}` | `{success}` | Only if the row is in the caller's audience; idempotent. |
| `POST /notifications/mark-all-read` | — | `{success, updated}` | Every unread row in the caller's audience. |
| `POST /notifications/send` | `{type, target, subject, body, link, from_system, event_key?}` | `{success, id}` | **Server-to-server or privileged users only** (see §9). Validate `type` ∈ §3.2, `target` required unless `type=all`, `from_system` ∈ configured list, lengths. |

Errors: `401` not authenticated · `403` not allowed to send · `400` validation
(`Invalid type`, `Target is required`, `Invalid system code`) · `500` logged, generic message.

**Session-auth gotcha (if you use cookies):** some frameworks only start the
session for API requests that carry an auth header or CSRF token (FIMS/PHPMaker
starts it only when `X-Authorization` is present). If so, the bell must always
send that header — even `X-Authorization: Bearer ` with an empty token — plus
`credentials: 'include'`. Test this explicitly (§10).

---

## 5. Sender library (server side)

One small class, shipped as a shared package/file every app includes.

```php
interface NotifierInterface {
    public function send(string $type, string $target, string $subject, ?string $body = null,
                         ?string $link = null, array $opts = []): ?string; // returns id or null
    public function toAll(string $subject, ?string $body = null, ?string $link = null): ?string;
    public function toSystem(int|string $appId, string $subject, ?string $body = null, ?string $link = null): ?string;
    public function toUser(int $userId, string $subject, ?string $body = null, ?string $link = null): ?string;
    public function toUserLevel(int $roleId, string $subject, ?string $body = null, ?string $link = null): ?string;
    public function toOffice(int $officeId, string $subject, ?string $body = null, ?string $link = null): ?string;
    public function toUserGroup(int $supervisorId, string $subject, ?string $body = null, ?string $link = null): ?string;
}
```

Rules:

- **Two transports, same interface:** `DbNotifier` (direct INSERT, for the hub
  itself or apps sharing its DB) and `HttpNotifier` (POST `{HUB_API}/notifications/send`
  with a service token, for apps that don't). Choose by config.
- Helpers map 1:1 to §3.2 types; `send()` rejects any other `type`.
- Parameterised SQL only. `from_system` comes from config (`APP_CODE`), not the URL.
- Call **after** the business transaction commits (or register an after-commit
  hook), so rolled-back actions never notify.
- Pass `opts['event_key']` for events that can fire twice (retries, double
  clicks) — the unique index de-duplicates.
- **Never throw** into business code: catch, log, return `null`.
- After a successful INSERT, publish to real-time (§6) — also non-throwing.

---

## 6. Real-time contract (optional)

### 6.1 Topics — one scheme for publisher **and** subscriber

```
{TOPIC_PREFIX}/notifications/{type}/{target}     e.g. app/notifications/user/42
{TOPIC_PREFIX}/events/{eventType}/{target}       UI refresh signals, not bell items
```

Browser subscribes for the logged-in user: `…/notifications/all/all`,
`…/notifications/user/{uid}`, one `…/notifications/userlevel/{roleId}` per role,
`…/notifications/office/{officeId}`, `…/notifications/system/{appId}`,
`…/notifications/usergroup/{reportsTo}`, and `{TOPIC_PREFIX}/events/#`.
Put topic building in **one shared helper** used by both sides.

### 6.2 Payload

```json
{ "id": "ntf_…", "type": "user", "target": "42", "subject": "…", "body": "…",
  "link": "/{APP_CODE}/Record?id=…", "from_system": "{APP_CODE}", "created_at": "2026-11-10T09:00:00+08:00" }
```

### 6.3 Server publish (EMQX REST example)

`POST {EMQX_API_URL}/api/v5/publish`, Basic auth `EMQX_API_KEY:EMQX_API_SECRET`,
body `{"topic": "...", "payload": "<json>", "qos": 1, "retain": false}`, 5 s
timeout, ≤3 retries, TLS verification on outside development.

### 6.4 Browser client

Connect over `wss://` (URL from config), subscribe per §6.1, reconnect with
backoff, and on each notification message dispatch **one** DOM event:

```js
document.dispatchEvent(new CustomEvent('app:notification', { detail: payload }));
```

Browser broker credentials must be **subscribe-only** and scoped to the user's
own topics (broker ACL), never the REST publish key.

---

## 7. Bell UI component

Included once per app layout (server include, web component, or framework
component). Behaviour:

- Bell icon + unread badge (hidden at 0, `99+` cap) + dropdown list (subject,
  body excerpt, `from_system`, `time_ago`, unread styling).
- Load on page open; **poll every 30 s**; refresh on `visibilitychange` →
  visible; refresh on `app:notification`.
- Click item → `mark-read` → navigate to `link` (only relative/same-origin links).
- "Mark all read" button; "See all" page using `recent?limit=200`.
- Public API: `window.Notifications = { refresh(), markAllRead() }`.
- Every fetch: `credentials: 'include'` + the auth header (§4 gotcha).
- Render text with `textContent`/escaping — never inject `subject`/`body` as HTML.
- Stop polling on logout / 401.

---

## 8. Configuration

| Key | Used by | Example |
|---|---|---|
| `APP_CODE` | sender library, bell | `ACCOUNTING-BAPD` |
| `NOTIFY_TRANSPORT` | sender library | `db` \| `http` |
| `NOTIFY_HUB_API` / `NOTIFY_SERVICE_TOKEN` | `HttpNotifier` | `https://host/UAC/api`, secret |
| `NOTIFY_ALLOWED_SYSTEMS` | hub `/send` validation | `UAC,ACCOUNTING-BAPD,FMD,…` |
| `EMQX_API_URL` / `EMQX_API_KEY` / `EMQX_API_SECRET` | server publish | empty ⇒ real-time off |
| `MQTT_WS_URL` | browser client | `wss://broker:8084/mqtt` |
| `NOTIFY_POLL_MS` | bell | `30000` |
| `NOTIFY_RETENTION_DAYS` | cleanup job | `90` |

Document every key; ship sensible defaults; log once at startup if real-time is disabled.

---

## 9. Implementation phases

| Phase | Deliverable | Done when |
|---|---|---|
| 1. Storage | §3.1 tables + indexes (migration) | Migration runs on a clean DB |
| 2. Read side | `getUserNotifications()` (§3.3) + `recent`/`count`/`mark-read`/`mark-all-read` | API tests pass for every audience type |
| 3. Sender | `NotifierInterface` + `DbNotifier` (+ `HttpNotifier` if multi-app) | A unit test sends to each type and reads it back |
| 4. Bell | §7 component included in each app layout | Badge/list/mark-read work with polling only |
| 5. Wiring | Calls at each business event (after commit, with `event_key`) | Each event listed in your event table notifies the right audience |
| 6. Real-time | §6 publish + browser client + broker ACLs | New items appear without refresh; broker down ⇒ still delivered by poll |
| 7. Ops | Retention job, test page, monitoring of publish failures | Documented runbook |

**Event table** (fill per project before Phase 5):

| Event | Audience (`type` → `target`) | Subject template | Link |
|---|---|---|---|
| Record submitted for review | `userlevel` → reviewer role | `{doc} {no} awaits your review` | `/{APP_CODE}/View?id=…` |
| Record returned | `user` → creator | `{doc} {no} was returned` | … |
| Record approved | `user` → creator | `{doc} {no} was approved` | … |

---

## 10. Security checklist

- [ ] Every hub endpoint requires authentication; `/send` additionally requires a service token or an explicit permission.
- [ ] No test/demo/broadcast endpoint is reachable in production.
- [ ] `mark-read` only affects rows in the caller's audience.
- [ ] Parameterised SQL everywhere; `type`/`from_system` validated against allow-lists.
- [ ] Bell escapes all text; `link` restricted to relative/same-origin URLs.
- [ ] Broker: publish credentials server-side only; browser credentials subscribe-only with per-user topic ACLs; TLS on.
- [ ] No PII/secrets in notification bodies beyond what the recipient may already see.

---

## 11. Testing

**Test page** (recommended — FIMS has one at `UAC/notification-test.php`): a
logged-in admin page that runs: session/auth present → `GET recent` → `POST send`
to self → `mark-read` → `mark-all-read` → verify `unread_count = 0`; plus a manual
send form (type, target, subject, body, from_system).

**Checklist**

- [ ] Each audience type reaches exactly its recipients (and no one else).
- [ ] Two users sharing a role row: one reading it doesn't mark it read for the other.
- [ ] Badge = `count` endpoint; mark-read / mark-all-read update it immediately.
- [ ] Broker down or `EMQX_API_URL` empty ⇒ action succeeds, item appears within one poll.
- [ ] Broker up ⇒ item appears without refresh (topic names match on both sides).
- [ ] Rolled-back transaction ⇒ no notification; duplicate event ⇒ one notification (`event_key`).
- [ ] Unauthenticated calls ⇒ 401; non-privileged `/send` ⇒ 403.

**Common failures**

| Symptom | Likely cause | Fix |
|---|---|---|
| Always `401` from the bell | Session not started for API calls / cookie not sent | Send the auth header (§4 gotcha) and `credentials: 'include'`; confirm the user has a hub session |
| Row saved but never shown | Sender wrote a `type` the audience query doesn't match | Use only §3.2 values via the library |
| Real-time never fires | Publisher and subscriber use different topic schemes, or broker config empty | One topic helper (§6.1); check `EMQX_*` / `MQTT_WS_URL` |
| `400 Invalid system code` | `from_system` not in `NOTIFY_ALLOWED_SYSTEMS` | Add the app code to config |
| Spinner never stops | JS error or blocked request | Browser console / network tab |

---

## Appendix A — FIMS reference implementation (verified 2026-11-10)

### A.1 File map

| Concern | FIMS file |
|---|---|
| Table | `uac.notifications` — `_assets/sql/01 - schema_table_structure/01 - UAC/01 - table/01 - uac_structure_data.sql` (CSV `read_users`, no audience CHECK, no indexes) |
| Hub routes | `UAC/app/routes/Notification.php` — `recent` (default 10, max 200), `count`, `mark-read`, `mark-all-read`, `send` (session-authenticated via `CurrentUserID()`), `user-level/{level}`, `test` |
| Hub service | `UAC/app/services/NotificationManager.php` — `getUserNotifications()` audience query (`:232-300`), `markAsRead()`, helpers `sendToAll/System/User/Office/UserLevel/UserGroup` |
| `/send` allow-lists | types `all, system, user, office, userlevel, usergroup`; systems `UAC, ACCOUNTING-GAD, ACCOUNTING-BAPD, ACCOUNTING-DCD, TREASURY, FMD, ACCOUNTING-PCDS, REGIONAL, DISTRICT` (`Notification.php:164-173`) |
| Bell | `_shared/notification.php` (included by `_shared/foot.php`; PHPMaker `ew-js-template` into `#ew-navbar-end`); polls 30 s, refreshes on `visibilitychange` and on the `appNotification` DOM event; sends `X-Authorization` + `credentials: 'include'` |
| Browser MQTT | `_shared/js/mqtt-notification.js` (loaded via each app's `JAVASCRIPT_FILES`); dispatches `appNotification` (`:231`) and `appEvent` |
| Per-app senders | `<APP>/app/lib/NotificationManager.php` copies; plus direct INSERT+publish in e.g. `ACCOUNTING-BAPD/app/services/JevWorkflowService.php` and `PeriodCloseService.php` |
| Docs / test | `_shared/NOTIFICATION_README.md`, `UAC/notification-test.php` |

### A.2 Where FIMS diverges from this plan (don't copy)

| Issue | Evidence | Plan section that avoids it |
|---|---|---|
| Per-app sender helpers write non-canonical types (`personal`, `userLevel`, `system`+`all`) that the hub's audience query never matches → saved but invisible | `ACCOUNTING-BAPD/app/lib/NotificationManager.php:173-186` vs `UAC/app/services/NotificationManager.php:255-286` | §3.2, §5 |
| Topic schemes differ: BAPD lib `app/notifications/{type}/{target}`, UAC + `JevWorkflowService` `nha/notifications/…`, browser subscribes `app/notifications/{system/all, personal, role, parent}` | `ACCOUNTING-BAPD/app/lib/NotificationManager.php:83`, `JevWorkflowService.php:1660`, `UAC/app/services/NotificationManager.php:85`, `_shared/js/mqtt-notification.js:166-190` | §6.1 |
| Duplicated sender code and direct INSERTs bypassing the library | per-app `NotificationManager.php` copies; `JevWorkflowService.php:1549-1660`; `PeriodCloseService.php:658-684` | §1 rule 2, §5 |
| String-built SQL (`sprintf` + `addslashes`) | `ACCOUNTING-BAPD/app/lib/NotificationManager.php:131-143` | §5 |
| Unauthenticated test/broadcast routes (hub `/test` hard-codes a user; several modules expose `/notifications/send`/`test` without auth) | `UAC/app/routes/Notification.php:265`; module process-flow docs | §10 |
| Hard-coded broker URL; TLS verification off | `mqtt-notification.js` (`wss://mqtt.itbsstudio.com:2096/mqtt`); `getHttpClient()` `'verify' => false` | §6.3–6.4, §8 |
| EMQX keys default to empty ⇒ real-time silently off | `ACCOUNTING-BAPD/src/config.development.php:29-32` | §8 |

### A.3 Corrections to `_shared/NOTIFICATION_README.md`

| README says | Code says |
|---|---|
| `GET /recent` returns the last **20** | Default **10**, `?limit=` clamped 1–200 (`UAC/app/routes/Notification.php:40-45`) |
| Real-time topic `lirmds/{type}/{target}` | `lirmds` appears nowhere in code; UAC publishes `nha/notifications/{type}/{target}` (`UAC/app/services/NotificationManager.php:85`) |
| Allowed `from_system`: UAC, GAD, BAPD, DCD, FMD, TREASURY | Also `ACCOUNTING-PCDS`, `REGIONAL`, `DISTRICT` (`Notification.php:164`) |
| Table columns list omits `is_read`, `created_at`; `user_id` = sender | Table has both; DDL comment describes `user_id` as the user with no FK constraint |
