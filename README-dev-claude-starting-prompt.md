# FIMS (NHA) — Claude Starting Prompt (for devs)

> **How to use:** in a new Claude Code session, the dev only mentions this file (`@README-dev-claude-starting-prompt.md`) and then states the task. This file is the **reference index**: it tells Claude which docs and code to read for the task, so the dev does not have to list them each time.
> Do **not** put credentials in this file. Secrets live in `.env` files only (see `.env.example` for the variable names).
>
> **Status:** v0.1. Only the API/route/service and page-creation conventions are covered so far. Sections marked _TODO_ are placeholders to fill in as more guidelines are written.

---

## 0. Instructions to Claude

You are working with a developer on **NHA FIMS (Financial Information Management System, a.k.a. AFIMS) of the National Housing Authority**: new features, fixes, reviews, and questions.

1. **Load references yourself.** Read this file, then use §6 (Reference map by task) to pick the docs and code for the dev's task and read them *before* planning or editing. Do not ask the dev for paths that §2 and §6 already give you. Start from `README.md` when the task fits no row in §6.
2. **Verify before you act.** The docs can lag the code. Check the actual file in `app/`, `_shared/` or `_assets/sql/` and cite `path:line`. Say clearly when something is unverified.
3. **Do the task the dev asked for.** If asked to implement or fix, make the change within §3's editable paths. If asked for advice, review, or a plan, don't edit. Ask only when a decision is the dev's to make (for example which database or schema a change targets). Otherwise pick the sensible default, say so, and continue. Keep scope tight and don't bundle cleanups.
4. **Always update `system_docs/`.** Whenever you add or change a feature, endpoint, page, service, table, or business rule, update the matching doc under `system_docs/` (`ERD/` for data model changes, `modules/` for module/feature docs) in the same task. Also update the other docs named in §6 (route list in `app/routes/README.md`, module list in `README.md`, etc.) so nothing drifts. If no matching doc exists yet, create one and say so. Mention in your final summary which docs you updated.
5. Never print, copy, or commit secrets (passwords, API keys, JWT secrets, DB logins, SMTP/EMQX keys). If you find one in a file, report the file and line only.
6. Never run SSH or touch real servers. Give server commands as code blocks for the dev to run.
7. Respond in the dev's language (English or Taglish is fine).

---

## 1. What the system is

- Multi-module web app for NHA financial operations: budget/fund tracking, procurement (PR → PO → DV), accounting, treasury, regional/district offices. Modules authenticate through a central **User Access Control (UAC)** layer.
- Stack: **PHPMaker 2024 (PHP 8, Slim 4)** + **PostgreSQL** (per-department schemas), Docker. JWT auth, EMQX MQTT (notifications/chat), BullMQ (background jobs), ONLYOFFICE (document editing), LIBIS integration (external Land Information & Billing System).
- PHP namespace of this project: **`PHPMaker2024\FIMS`**. Project file: `_source/FIMS.pmp`.
- This repo is derived from the `__BASE__` template (`_source/_BASE_.pmp`, `_source/PROJECT_SETUP.md`). Some files still carry leftover placeholders (`__BASE__`, `ITBS_STANDARD_APP`); when you touch such a file, replace them with the real names.
- The root `README.md` describes the full multi-module FIMS and lists folders (UAC, FMD, GSD, ACCOUNTING-*, TREASURY, …). **Check which of those actually exist in this repo before relying on it** — this repo currently holds only the `app/`, `_shared/`, and generated PHPMaker folders.

## 2. Where things are

| Path | Contents |
|---|---|
| `app/api.php` | API entry: defines `$jwtMiddleware`, `$accessMiddleware`, and `include`s each route file **by hand**. |
| `app/routes/` | Slim route files (`notifications.php`, `service.php`). Documented in `app/routes/README.md`. |
| `app/lib/` | Service/library classes (`ApiService.php`, `NotificationManager.php`). |
| `app/lib/GlobalService.php` | Reusable helper/utility functions shared by multiple services. Currently empty. Add a function here only if 2+ services will use it; otherwise keep it in that feature's own service. |
| `app/services/` | `login.php`, `main-dashboard.php` — verify what these are before adding more. |
| `app/pages/` | PHPMaker custom pages (PHP + inline JS): `login.php`, `main-dashboard.php`. |
| `app/global.php` | PHPMaker global hooks. |
| `app/global_script.php` | Shared page partial (PHP + HTML + JS) `include`d by multiple custom pages via `include(dirname(__DIR__, 1) . "/global_script.php");`. For shared modals/JS only; currently empty. A change affects every including page, so grep `global_script` first. |
| `_shared/` | Code shared across modules: `PagePermission.php`, `auth.php`, `ldap-auth.php`, `notification.php`, `storage.php`, `workflow-manager/`, `chat-widget/`, `bullmq-manager/`, `api/api-client.js`, branding helpers, shared CSS/JS. See `_shared/README-PagePermission.md`, `_shared/NOTIFICATION_README.md`. |
| `_guidelines/` | Dev conventions (see §4 and §6). |
| `_assets/sql/` | SQL (see `_assets/sql/README.md`). |
| `_assets/System Requirements/` | SRS `.docx` per department (Accounting, District, FMD, Regional, TD). |
| `_source/` | PHPMaker project files (`FIMS.pmp`) and `PROJECT_SETUP.md`. |
| `system_docs/` | `ERD/`, `modules/`. |
| `models/`, `views/`, `controllers/`, `src/`, `lang/` | **PHPMaker-generated.** See §3. |
| `swagger/`, `app/lib/ApiService.php` | OpenAPI generation (`GET /Service`, `_shared/Swaggerize.php`). |
| `README-DEPLOY.md`, `README-Docker.md`, `README-installation.md`, `Dockerfile`, `docker-compose.yml`, `_docker/`, `web.config`, `.htaccess` | Deploy / environment setup. |

## 3. Hard rules

**Editable paths** (everything else is generated or third-party): `app/`, `_shared/`, `_guidelines/`, `_assets/`, `system_docs/`, `_source/FIMS.pmp`.
- `models/`, `views/`, `controllers/`, `src/` and `lang/` are generated by PHPMaker. Direct edits are a *temporary bypass only* and must be mirrored into the `.pmp` before the next generate.
- For simple CRUD, use PHPMaker's default output unmodified. Hand-code a page only when the default page can't do the job.
- Do not edit vendored front-end libraries (`adminlte3/`, `bootstrap5/`, `ckeditor/`, `colorbox/`, `jquery/`, `plugins/`, `js/` third-party files).

**Databases**
- Treat any shared / production database as **SELECT only**. Never UPDATE/INSERT/DELETE/DDL against it from Claude. Schema changes and data fixes are written as SQL files for the dev to run.
- Ask which database/schema a task targets (local, shared dev, production) before writing SQL.

**Environment**
- Config comes from `.env` (copy of `.env.example`, loaded by `_shared/load-env.php`). Never read, print, or commit `.env`.
- Auth tokens reach the browser as `ew.API_JWT_TOKEN`; API calls send it in the `X-Authorization: Bearer …` header.

## 4. Code conventions to follow

Full detail lives in the two guideline files — **read the relevant one before writing code**:

- API routes & services → `_guidelines/api-route-service-guidelines.md`
- Pages → `_guidelines/page-creation-guidelines.md`

Condensed rules:

**New endpoint**
- Route file in `app/routes/<feature-name>.php`, namespace `PHPMaker2024\FIMS`, then `include` it in `app/api.php` (there is no glob loader). Update `app/routes/README.md`.
- Business logic in a service class under `app/lib/` (the guideline's convention); the route closure only parses input, calls the service, and writes the response.
- Auth is explicit on every route: `->add($accessMiddleware)->add($jwtMiddleware)` (jwt added last so it runs first). Note `$accessMiddleware` in `app/api.php` is currently a **pass-through stub** and `GET /Service` has its jwt commented out — don't assume routes are protected until you've checked. For per-page permission use `_shared/PagePermission.php` (`README-PagePermission.md`).
- Reusable helper/utility functions go in `app/lib/GlobalService.php` (only when shared by multiple services). Anything feature-specific stays in its own service file.
- Responses are JSON `{success, data?, message?}`. Return 400 for bad input, 401/403 for auth, 500 for server errors; don't leak internal exception messages on 500.

**Database access**
- Use PHPMaker helpers: `ExecuteScalar`, `ExecuteRow`, `ExecuteRows`, `ExecuteRowAssociative`, `ExecuteRowsAssociative`, `Execute`. Pass connection `"DB"` as the last argument.
- **Namespace prefix:** inside `app/lib/*` use `App\ExecuteRow(...)`, `App\QuotedValue(...)`, `App\DataType::NUMBER`. In `app/api/`, `app/routes/` and `app/pages/` use them without the prefix. (Some examples in the guideline files omit `App\` inside services — follow the rule in the Namespace table, not the example.)
- There are no prepared statements: wrap **every** value in `QuotedValue($v, DataType::X, "DB")`; never interpolate raw input; cast ids/limits/offsets with `(int)`. NULL checks are written directly (`IS NULL`).
- Multi-table writes need an explicit transaction (`BEGIN` / `COMMIT` / `ROLLBACK`). Services share one connection, so check before nesting a service call that opens its own transaction.
- Select specific columns, use `LIMIT`/pagination, and log failures with `LogError(...)` (SQL for debugging with `Log(...)`).
- The guidelines' examples use the `UAC` namespace and `/UAC/api/...` URLs — **substitute this project's namespace (`FIMS`) and base path.**

**Pages**
- Page = PHP block (namespace, data loading) + HTML + `<script>` + `<style>` in one file under `app/pages/`.
- Pick the data pattern deliberately: server-side queries for data needed at first paint; `fetch` to `/…/api/…` for dynamic/interactive data; hybrid for both.
- Markup/JS needed by 2+ pages goes in `app/global_script.php` (included by the pages); page-specific code stays in the page.
- Always send `X-Authorization: Bearer ' + ew.API_JWT_TOKEN`, check `result.success`, and handle errors with `ew.alert(...)`.
- Gate custom pages with permission checks (`AllowView(...)`, `PagePermission`).
- UI: AdminLTE/Bootstrap 5 cards, tables (`table-responsive`), modals via `data-ew-action="modal"`, refresh data on the `ew.on("success", …)` event.
- **Escape all output.** `HtmlEncode(...)` in PHP; `textContent` (not `innerHTML`) in JS, or an `escapeHtml` helper when building HTML strings.

## 5. Database changes

Full rules: `_assets/sql/README.md` (read it before writing any SQL). Annotated templates for each kind of file (table, alter, view, function, seed, fix, truncate): `_guidelines/sql-structure-sample.sql`. Summary:

- **Convention:** SQL is split into numbered phase folders under `_assets/sql/` and run in natural-sort order of the full path. Each change is a **new numbered file** in the right phase/module folder. Never edit an already-run file to add a column (it breaks DBs that already ran it); add a new file in phase `04` instead. This differs from BPLS, which appends to ONE_SHOT files.
- **Phases:** `00 - public` (extensions/schemas) → `01` tables (`CREATE TABLE` only) → `02` views → `03` functions → `04` alter table / alter view (all incremental changes) → `05` sample data → `06` truncate (dev only, commented out) → `07` maintenance (runs last). `07 - CROSS-MODULE-DEMO` is not run by the installer.
- **Where it goes:** new table → `01 - schema_table_structure/[module]/01 - table/`; add/rename/drop column, type change, FK → `04 - schema_alter_table_structure/[module]/04 - alter_table/`; new view → `02 - schema_view_structure/[module]/02 - views/`; change an existing view → `04 - …/[module]/05 - alter_view/`; function → `03 - schema_function_structure/[module]/03 - function/`; seed data → `05 - schema_sample_data_structure/[module]/05 -sample_data/` (no space after the dash).
- **Naming:** zero-padded number prefix + ` - ` + name for every file and folder (e.g. `45 - vouchers_add_remarks.sql`). Use the next unused number in that folder; never reuse one. Module folder numbers differ per phase (e.g. ACCOUNTING is `03` in phase 01 but `02` in phase 04), so copy the numbering of the phase you are editing.
- **Table format (house style):** banner comment (`-- ===` / `-- TABLE_NAME TABLE` / `-- ===`), then an empty `CREATE TABLE IF NOT EXISTS schema.t();`, then one `ALTER TABLE … ADD COLUMN IF NOT EXISTS …` listing every column (one per line), then a `-- t indexes` comment with `CREATE INDEX IF NOT EXISTS idx_<table>_<col>`. Composite index is named `idx_<table>_composite`. See `_guidelines/sql-structure-sample.sql`.
- **Idempotent:** use `IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS`, and `DROP VIEW IF EXISTS … CASCADE` before `CREATE VIEW`. The installer runs with `ON_ERROR_STOP=1`, so one error stops everything.
- **FK trap:** `01 - UAC` sorts before `1.1 - shared`, so a `uac` table must not FK into `shared` in its `CREATE TABLE`. Add the FK in phase `04`.
- **Consolidated build:** `_assets/sql-consolidated/` is a derived artifact. Edit source files only, never the consolidated ones.
- **Schemas:** `shared`, `uac`, `fmd`, `accounting`, `treasury`, `workflow`, `regional`, `district`, `integration`. Permissions live in `uac.user_level_permissions`.
- Claude writes the SQL file; **the dev runs it** (`npm run sql` → "Run only NEW SQL files"). Ask which database a task targets. Update `system_docs/ERD/` when tables or columns change (§0 item 4).
- **Verify first:** `_assets/sql/README.md` describes the full FIMS repo (`install_system.js`, `consolidate_sql.js`, 9 phase folders, ~371 files). In this repo only that README is present so far. Check that the folders and installer exist before relying on them.

## 6. Reference map by task

Paths are relative to the repo root (`D:\PROJECTS\fims-nha`). Read the doc, then the code. If a listed file doesn't exist, check the folder before assuming it moved.

| Task area | Docs to read | Code to read |
|---|---|---|
| New / changed API endpoint, service | `_guidelines/api-route-service-guidelines.md`; `app/routes/README.md`; `_source/PROJECT_SETUP.md` | `app/api.php`, `app/routes/*`, `app/lib/*` |
| New / changed custom page | `_guidelines/page-creation-guidelines.md` | `app/pages/*`, `_shared/css/*`, `_shared/foot.php`, `_shared/api/api-client.js` |
| Page / route permissions | `_shared/README-PagePermission.md` | `_shared/PagePermission.php`, `_shared/auth.php` |
| Login, JWT, LDAP | — _(TODO)_ | `app/pages/login.php`, `app/services/login.php`, `_shared/auth.php`, `_shared/ldap-auth.php` |
| Notifications, MQTT, chat | `_shared/NOTIFICATION_README.md`, `_shared/chat-widget/README.md` | `app/lib/NotificationManager.php`, `app/routes/notifications.php`, `_shared/notification.php`, `_shared/mqtt-*.js` |
| Approval workflows | — _(TODO)_ | `_shared/workflow-manager/` |
| Background jobs | — _(TODO)_ | `_shared/bullmq-manager/` |
| File storage / uploads | — _(TODO)_ | `_shared/storage.php`, `files/` |
| Document branding / printed forms | `_shared/branding/README.md` | `_shared/DocumentBranding.php`, `_shared/docx-branding.js`, `_shared/nha-logos/` |
| API docs (OpenAPI) | — | `_shared/Swaggerize.php`, `app/lib/ApiService.php`, `app/routes/service.php`, `swagger/` |
| Business requirements | `_assets/System Requirements/*.docx` (SRS per department) | — |
| Database / ERD | `_assets/sql/README.md`; `system_docs/ERD/` | — |
| Module overview | `README.md`; `system_docs/modules/` | — |
| Docker, deploy, install | `README-DEPLOY.md`, `README-Docker.md`, `README-installation.md` | `Dockerfile`, `docker-compose.yml`, `_docker/`, `web.config`, `.htaccess` |
| PHPMaker project / regeneration | `_source/PROJECT_SETUP.md` | `_source/FIMS.pmp` |

**For any task, also check:** §3 (hard rules), §4 (conventions), and whether other FIMS modules or the shared `_shared/` code are affected.

## 7. Doing the task well

- Name the **module/schema**, the **tables**, the **route/service/page files**, and the **user levels / permissions** a change touches.
- Call out blast radius: other callers of a service, PHPMaker regeneration, `_shared/` consumers (changes there affect every module), and the SQL files the dev must run.
- Prefer the smallest change that fits existing patterns. Suggest cleanup separately; do not bundle it.
- Update `system_docs/` (and the matching docs from §6) every time behavior changes. This is not optional.
- End with: what you changed, what you verified, what you did not, and what the dev should run to test.
