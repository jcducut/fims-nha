# FIMS NHA — Developer Guide

> Status: this guide documents the **actual code in this repository** as of **2026-11-09** (branch
> `documents`; the Inspection and Acceptance Report module — §8.4 / §7.5 — added 2026-11-10). It is deliberately narrower than `README.md` at the repo root — see
> [A note on other documentation in this repo](#a-note-on-other-documentation-in-this-repo) for why
> the two disagree, and don't be misled by the older doc's module list.

## 1. What this repo is

**FIMS** (also called **AFIMS** — Accounting and Financial Information Management System) is the
National Housing Authority's financial system. This specific checkout is a **from-scratch rebuild**:
a single, unified PHPMaker 2024 + Slim 4 project named `FIMS`, started 2026-11-06. It currently
implements a slice of the full target scope — User Access administration plus the early Budgeting
and Procurement workflows of the Financial Management Department. Everything lives in **one**
PHP namespace (`PHPMaker2024\FIMS`), **one** set of `controllers/`, `models/`, `views/` folders, and
**two** Postgres schemas (`uac`, `fims`) — there is no per-module folder split (no `UAC/`, `FMD/`,
`ACCOUNTING-*/`, `TREASURY/`, `REGIONAL/` directories in this repo).

The full intended scope of NHA's financial system — Accounting Department, District offices, FMD,
Regional offices, Treasury Department — is specified in the SRS documents under
`system_docs/modules/*.docx` (and duplicated at `_assets/System Requirements/`). Treat those as the
**target roadmap**, and this guide as **what's actually built today**. Section
[12](#12-roadmap-what-isnt-built-yet) maps the gap.

---

## 2. Architecture at a glance

| Layer | Technology |
|---|---|
| Language | PHP 8.1+ |
| Code generator | PHPMaker 2024 (generates `controllers/`, `models/`, `views/`) |
| Web framework | Slim 4 (routing, middleware, DI container) |
| ORM | Doctrine DBAL / ORM (used by the generated layer; hand-written services use raw SQL helpers instead — see §5) |
| Database | PostgreSQL, schemas `uac` and `fims` |
| Auth | JWT (`firebase/php-jwt`), cookie + header bearer token |
| Frontend | Bootstrap 5, AdminLTE 3, jQuery, DataTables, SweetAlert2 |
| Containerization | Docker Compose (`docker-compose.yml`) |
| Reverse proxy | Nginx (`nginx.conf`), app on `http://localhost:8090` |

### Two front controllers

- **`index.php`** (repo root) — renders pages (`views/*.php` wrapping `app/pages/*.php`). Base path `/`.
- **`api/index.php`** — a *separate* Slim app, base path `/api`. Every route registered in
  `app/routes/*.php` is reached externally with an `/api` prefix, e.g. a route declared as
  `/vendors/all` is actually `GET /api/vendors/all`.

Both share the same `src/` framework code, DI container (`src/definitions.php`), and permission
tables — they're two entry points into one codebase, not two apps.

### Route registration (PHPMaker side)

Custom pages are wired through PHP 8 attributes on controller methods (`#[Map(...)]`,
`#[Get(...)]`, etc. in `src/Attributes/`), **not** a central route file. These attributes are
scanned and cached into `log/cache/RouteAttributes.php` (and `ApiRouteAttributes.php`) by:

```bash
composer routes
```

(defined in `composer.json` → `"routes": "PHPMaker2024\\FIMS\\RouteAttributes::dispatch"`). Run this
after adding a new controller or changing a `#[Map(...)]` route pattern. In Docker:

```bash
docker exec fims-nha-php sh -lc "composer routes"
```

`_docker/php/php.ini` sets `opcache.validate_timestamps = 1` with a 2-second revalidate frequency —
unlike some sibling NHA projects, you do **not** need to flush OPcache by hand after editing PHP;
just wait ~2 seconds or reload the page.

### API route registration (Slim side, `app/routes/`)

Unlike the PHPMaker attribute routes above, files in `app/services/` and `app/routes/` are
**auto-discovered** — `app/api.php` globs every `*.php` file in both folders (skipping anything
starting with `_`) and `require_once`s it. **You do not need to manually include a new
service/route file anywhere** — just drop it in the folder:

```php
// app/api.php (excerpt)
foreach (glob($appBase . 'services/[!_]*.php') ?: [] as $f) { require_once $f; }
foreach (glob($appBase . 'routes/[!_]*.php') ?: [] as $f) { require_once $f; }
```

This is simpler than the manual two-include pattern used in older NHA FIMS modules — if you've
worked on a predecessor project, drop that habit here.

---

## 3. Directory map

```
fims-nha/
├── app/
│   ├── api.php           # Slim API bootstrap — JWT/access middleware, auto-discovery of services+routes
│   ├── global.php        # App config / DB connection bootstrap
│   ├── global_script.php
│   ├── preloader.php
│   ├── lib/              # Hand-written helpers not auto-discovered (GlobalService.php, etc.)
│   ├── pages/            # Custom page bodies (HTML+PHP+JS), one file per custom feature
│   │   ├── form/          # Create/edit form fragments for budget features
│   │   ├── readone/       # Detail/view fragments
│   │   └── po/            # Purchase Order page/form/readone trio
│   ├── routes/            # Slim route files (auto-discovered, see §2)
│   └── services/          # Business-logic service classes (auto-discovered)
├── controllers/          # PHPMaker-generated — one *Controller.php per page/table (58 files)
├── models/                # PHPMaker-generated page/table classes (191 files)
├── views/                 # PHPMaker-generated view wrappers (thin — include the app/pages/ file)
├── src/                   # Framework: DI definitions, attributes, middleware, user-level settings
├── _shared/               # Cross-cutting: auth.php, PagePermission.php, workflow-manager/, notification.php, file-manager/, document/
├── _guidelines/            # House style guides — READ THESE before writing new pages/routes (§5)
├── _assets/               # SQL source tree + SRS Word docs (see §11)
├── system_docs/           # This guide, ERD notes, module SRS docs, flowcharts
├── install_system.js      # SQL + composer installer (see caveats in §13)
├── docker-compose.yml     # php / nginx / postgres / redis / minio / adminer
└── composer.json          # `composer routes` regenerates the attribute route cache
```

---

## 4. How a feature gets built here: two patterns

Every page in this app falls into one of two categories. Knowing which one you're looking at (or
need to build) saves a lot of confusion.

### Pattern A — PHPMaker-generated CRUD (master/reference tables)

For simple tables that just need Add/Edit/Delete/List/View, PHPMaker generates the full set:
`<Table>Add.php`, `<Table>Edit.php`, `<Table>Delete.php`, `<Table>List.php`, `<Table>View.php`,
`<Table>Grid.php` across `controllers/`, `models/`, `views/`. Examples in this repo: `Users`,
`Departments`, `Positions`, `PositionCategories`, `PlantillaItems`, `Offices`, `UserOffices`,
`DocumentAssetTypes`, `DocumentHeaderAssets`, `DocumentHeaderSlots`, `Systems`, `DepartmentSystems`,
`ApiSystems`, `ApiResources`, `ApiLogs`, `AuditLogs`, `JwtSessions`, `UserLevels`,
`UserLevelPermissions`, `UserLevelAssignments`, `Psgc`.

**Don't hand-edit these** unless you know PHPMaker's generation model — they're meant to be
regenerated from the `.pmp` project file.

### Pattern B — hand-built custom single-page feature (everything else)

This is how almost every business page in this repo works (`BudgetReqPage`, `ChartOfAccountsPage`,
`VendorsPage`, the `po/` Purchase Order trio, `IarModule`, `MainDashboard`, `UserAccess`,
`DepartmentManagement`, …). There is no generated Add/Edit/List split — one controller/model/view
triplet renders one page, and that page does its own CRUD via `fetch()` calls against a Slim API
route. Recipe, with a real example (`BudgetReqPage`):

1. **Service** — `app/services/BudgetReqService.php`. Plain class with methods like `getAll()`,
   `create($data)`; uses `ExecuteRowsAssociative` / `ExecuteScalar` / `QuotedValue` / `DataType`
   (no namespace prefix needed here — see the table in §5).
2. **Route** — `app/routes/budget_req.php`. `$app->get(...)`, `$app->post(...)` etc., chained with
   `->add($accessMiddleware)->add($jwtMiddleware)`. **No manual wiring needed** — auto-discovered
   (§2).
3. **Page body** — `app/pages/budget-req-page.php`. Plain PHP+HTML+JS. Reads `CurrentUserLevel()`
   for role-based UI gating (see §5), fetches data client-side with
   `'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN`.
4. **Page class** — `models/BudgetReqPage.php`. Clone of an existing single-page model
   (e.g. `models/MainDashboard.php`), renaming `PageObjName` / `CurrentPageName` / breadcrumb /
   heading. PSR-4 autoload — no `composer dump-autoload` needed for a new class under `models/`.
5. **View** — `views/BudgetReqPage.php`. Thin wrapper: sets `$<Name> = &$Page;`, calls
   `$Page->showMessage()`, then `include`s the `app/pages/<name>.php` body.
6. **Controller** — `controllers/BudgetReqPageController.php`:
   ```php
   class BudgetReqPageController extends ControllerBase
   {
       #[Map(["GET", "POST", "OPTIONS"], "/BudgetReqPage[/{params:.*}]", [PermissionMiddleware::class], "custom.budget_req_page")]
       public function custom(Request $request, Response $response, array $args): Response
       {
           return $this->runPage($request, $response, $args, "BudgetReqPage");
       }
   }
   ```
7. **Register the DI binding** — `src/definitions.php`: `"budget_req_page" => \DI\create(BudgetReqPage::class),`
8. **Register the menu entry** — `views/menu.php`:
   ```php
   $sideMenu->addMenuItem(81, "mi_budget_req_page", $Language->menuPhrase("81", "MenuText"),
       "BudgetReqPage", -1, "", AllowListMenu('{FIMS}budget-req-page.php'), false, false, "", "", false, true);
   ```
9. **Register for permission sync** — `src/userlevelsettings.php` → `$USER_LEVEL_TABLES` /
   `$USER_LEVEL_PRIVS` so the page shows up in the permission matrix and gets default-deny rows for
   `Anonymous`/`Default`.
10. **Regenerate the route cache**: `composer routes` (§2). Route is then `/BudgetReqPage`.

Some features skip the model/view/controller scaffold entirely and are **pure API + page**, with no
PHPMaker table model at all — `Vendors` and `Purchase Orders` are the clearest examples: there is no
`models/Vendors.php`; `VendorService.php` + `app/routes/vendor.php` + `app/pages/vendors-page.php`
are the whole feature. Use this lighter form when the page is fully self-contained and doesn't need
PHPMaker's page-object conveniences (breadcrumbs, built-in export, etc.).

---

## 5. Conventions you need to know

### Namespace prefix on data helpers

| Where | Prefix | Example |
|---|---|---|
| `app/services/*.php`, `app/lib/*.php` | `App\` | `App\ExecuteRow(...)` |
| `app/pages/*.php` (custom page bodies) | none | `ExecuteRow(...)` |
| `app/routes/*.php` (API route closures) | none | `ExecuteRows(...)` |

### SQL parameter quoting — always `QuotedValue()`

```php
$sql = "SELECT * FROM fims.vendors WHERE vendor_code = " .
    QuotedValue($code, DataType::STRING, "DB");
```
Never concatenate raw user input into SQL. `DataType` constants: `STRING`, `NUMBER`, `BOOLEAN`,
`DATE`, `TIME`, `MEMO`, `BLOB`, `GUID`, `XML`.

Full query-pattern reference (ExecuteScalar/ExecuteRow/ExecuteRows/Execute/transactions, response
formatting, pagination): `_guidelines/api-route-service-guidelines.md`. Page-body patterns (hybrid
server+client data loading, modals, DataTables wiring): `_guidelines/page-creation-guidelines.md`.

### Permissions

Two layers, used together:

1. **Coarse, page-level**: `AllowListMenu('{FIMS}<page-or-table>.php')` in `menu.php`, checked
   against `uac.user_level_permissions` (keyed by the `{FIMS}` project tag + table/page name) for
   the logged-in user's level. `user_level_id = -1` (superadmin) bypasses every check.
2. **Fine-grained, in-page**: custom pages read `CurrentUserLevel()` directly and hide/disable
   controls per numeric level — e.g. `app/pages/budget-req-page.php` hard-codes "level 2 = Budget
   Officer can't Add/Edit/Delete" and "level 8 = Budget Head sees Budget Preview". There's no
   declarative role system yet for this fine-grained layer — it's inline `if` checks per page.
   Follow the existing comment style there (name the role, explain the gate) when adding a new one.

Route-level enforcement: `[PermissionMiddleware::class]` in the controller's `#[Map(...)]` attribute
(page routes) and `$accessMiddleware`/`$jwtMiddleware` chained onto Slim routes (API routes). Note:
as of this writing `$accessMiddleware` in `app/api.php` is a pass-through stub (see its comment) —
API routes are JWT-authenticated but not yet permission-gated at the route layer; the enforcement
today is the in-page `CurrentUserLevel()` checks described above. Don't assume the route itself
blocks unauthorized calls.

### Default dev login

`username/password: superadmin / superadmin123` (seeded by
`_assets/sql/00 - uac/04 - seed_data/01 - superadmin_user.sql`). Passwords are stored
case-folded — the hash is of the **lowercased** password — so don't hand-write a mixed-case bcrypt
seed.

---

## 6. `app/` in depth: two generations of service wiring

`app/` is where almost all hand-written logic lives (pages, services, routes, shared libs) — the
rest of the repo (`controllers/`, `models/`, `views/`, `src/`) is PHPMaker-generated scaffolding
(§4). Within `app/`, know which wiring generation a file belongs to:

**`app/services/` (current convention — use this for new work).** Auto-discovered by `app/api.php`
(§2) — no manual registration needed.

**`app/lib/` (older convention, inherited from a predecessor module).** `app/global.php` and
`app/api.php` manually `include_once`/`require_once` these — the auto-discovery glob does **not**
touch `app/lib/`. Of the 6 files there, 4 are correctly wired (`GlobalService.php` via `api.php`;
`ApiService.php`, `NotificationManager.php`, `UserAccessService.php` via `global.php`) and 2 are
not — see §7.1. If you add a new file to `app/lib/`, it will **not** load automatically; either
wire it by hand in `global.php` or just put it in `app/services/` instead.

Two other bootstrap files: `app/global_script.php` is an opt-in shared HTML/JS partial (`include`d
by individual pages that need it — currently empty, meant for cross-page modals/helpers as they
arise), and `app/preloader.php` is the full-page loading-overlay markup/JS, included globally from
`_shared/foot.php`.

---

## 7. Known issues in `app/` (verified against a running instance, 2026-11-09)

These were found by reading every file under `app/` and live-testing suspicious wiring against the
Docker stack — not just static inspection. Fix-worthiness roughly in priority order.

### 7.1 `DepartmentManagementService` / `PositionManagementService` are never loaded
**Confirmed via `curl` against the running stack:**
```
POST http://localhost:8090/api/department-management/departments
→ {"error":{"type":"Error","description":"...Class \"PHPMaker2024\\FIMS\\Service\\DepartmentManagementService\" not found"}}

POST http://localhost:8090/api/position-management/categories
→ {"error":{"type":"Error","description":"...Class \"PHPMaker2024\\FIMS\\Service\\PositionManagementService\" not found"}}
```
**Effect:** Add/Edit Department on `department-management.php`, and Add Category/Position on
`position-management.php`, are all currently broken (the fatal above, surfaced to the user as a
generic failure toast). **Fix:** add to `app/global.php`, next to the three lines that already work:
```php
include_once(dirname(__DIR__, 1) . "/app/lib/DepartmentManagementService.php");
include_once(dirname(__DIR__, 1) . "/app/lib/PositionManagementService.php");
```
Not applied yet — flagging for a decision before touching live behavior.

### 7.2 Two pages link a stylesheet path that doesn't exist
`app/pages/cob-page.php` and `app/pages/budget-revision-page.php` both link
`href="/css/nha-fims.css"`. That file doesn't exist — confirmed `css/nha-fims.css` is absent at repo
root (the `css/` folder exists but doesn't contain it). The real file is `_shared/css/nha-fims.css`,
which every other custom page (e.g. `budget-req-page.php`) correctly references. Effect: both pages
likely render without the shared NHA theme (404 on the stylesheet request).

### 7.3 `app/pages/form/budget-allocation-form.php` targets a different, non-existent API
Declares `namespace PHPMaker2024\FMD;` (every other file in `app/` uses `PHPMaker2024\FIMS`) and
calls `Config('API.FMD_URL')`. `.env` does define `API_FMD_URL` (underscore form — every other API
config key in this repo uses the dot form, e.g. `API.FIMS_URL`). Looks like an unported leftover
from a predecessor FMD module. Not confirmed as a live crash (sits behind login) — don't build on
this page without fixing the namespace/config mismatch first.

### 7.4 `app/routes/crud.php` + `app/services/crudServices.php` are dead code pointing at a schema that doesn't exist
Both declare the leftover template namespace `PHPMaker2024\FMD` / `PHPMaker2024\FMD\Service`.
`CRUDServices` hardcodes `$table = 'shared.programs'` — but **no `shared` schema exists anywhere in
this repo** (only `uac` and `fims`). Grepped all of `app/pages/**` for any call to `/crud/...` —
**zero matches**. Live (auto-discovered) but uncalled; if invoked it would SQL-error on the missing
schema, not the class-not-found error from §7.1. Treat as dead code — delete it or repoint it at a
real `fims` table if the generic-CRUD idea is still wanted. Don't copy its namespace into new
service/route files — use `PHPMaker2024\FIMS`.

### 7.5 `memo-req-page.php` is registered but empty (and `AirModule` leftovers)
`app/pages/memo-req-page.php` is a **zero-byte file** (confirmed via direct size check). It is fully
wired at the framework level (registered in `src/definitions.php`, menu entry in `views/menu.php`), so
it renders a blank page rather than erroring. Pure scaffolding — don't assume the feature is
implemented just because it appears in the menu.

`app/pages/air-module.php` (the other former empty stub) is gone: the module was built out as the
**Inspection and Acceptance Report (IAR)** module — see §8.4 — in `app/pages/iar-module.php`, served
by the PHPMaker page **`IarModule`** (`controllers/IarModuleController.php`, `models/IarModule.php`,
`views/IarModule.php`, menu item `mi_IARModule`). The original `AirModule` controller/model/view
(`*AirModule*`) are still in the tree from the earlier scaffold; `views/AirModule.php` was repointed at
`iar-module.php`, but nothing in the menu uses them any more — safe to remove once the `.pmp` project
no longer generates them.

> If you're fixing any of the above: §7.1 and §7.2 are independent, low-risk, verified fixes. §7.4
> is a safe delete (confirmed zero callers). §7.3 needs a decision on whether the page is active
> work before fixing. §7.5 isn't a fix, just a heads-up. None of these have been applied — this
> guide only reports what's there.

---

## 8. Feature reference

There's no formal module/folder split yet (see §1), so "module" here means a logical grouping of
pages that work together. Most `app/services/` + `app/routes/` pairs follow one uniform REST shape
(all reached with an `/api` prefix externally, e.g. `/vendors/all` → `GET /api/vendors/all`):

```
GET    /{feature}/all          — unpaginated, for dropdowns
GET    /{feature}/list         — paginated (page, limit) + filters, usually returns a `summary` block too
GET    /{feature}/read/{id}    — one record
POST   /{feature}/add          — create
PUT    /{feature}/update/{id}  — update
DELETE /{feature}/delete/{id}  — delete (often blocked if referenced elsewhere — soft-delete via status toggle instead)
```
Deviations are called out per-feature below.

### 8.1 User Access & Administration (`uac` schema)
| Feature | Files | Purpose |
|---|---|---|
| Login / session | `app/pages/login.php`, `views/Login.php`, `models/Login.php`, `_shared/auth.php` | Standalone page (not wrapped by the shared header/footer); redirects to `$_SESSION['nha_home_url']` (default `/MainDashboard`) if already logged in. Also reads `_shared/system.php` for a legacy "subsystem" dropdown (see §13) |
| `MainDashboard` | `app/pages/main-dashboard.php` | User/system/user-level counts + a 6-month user-growth chart, queried directly against `uac.users`/`systems`/`user_levels` |
| `UserAccess` | `app/pages/user-access.php` (88KB — largest page in `app/`) + `app/routes/user-access.php` + `app/lib/UserAccessService.php` | User/role CRUD, permission-matrix editing, user-level assignment — fully documented in `app/routes/README.md` |
| `UserManagement`, `Users`, `UserLevels`, `UserLevelPermissions`, `UserLevelAssignments` | generated CRUD (Pattern A) | Underlying tables behind `UserAccess`'s UI |
| `DepartmentManagement` | `app/pages/department-management.php` + `app/routes/department-management.php` + `app/lib/DepartmentManagementService.php` | **Broken — see §7.1.** Department master data, hierarchy (cycle-checked on update). KPI cards: total/active departments, budget-unit count, top-level dept count |
| `PositionManagement` | `app/pages/position-management.php` + `app/routes/position-management.php` + `app/lib/PositionManagementService.php` | **Broken — see §7.1.** Position categories + positions + `PlantillaItems`. KPIs: total/active/inactive positions, category count |
| `Departments`, `Positions`, `PositionCategories`, `PlantillaItems`, `Offices`, `UserOffices` | generated CRUD | Supporting master tables |
| `SecurityManagement` | `app/pages/security-management.php` | Read-only consolidated dashboard (users/departments/positions/user-levels breakdowns + links into the pages above) — no CRUD of its own, no dedicated route/service |
| `SignatoryManagement` | custom page | Signatory assignment for approvals/printed forms |
| `Systems`, `SystemsMaster`, `DepartmentSystems`, `ApiSystems`, `ApiResources`, `ApiLogs` | generated CRUD | System/module registry + API inventory (scaffolding for a future multi-system model — currently only one system, `FIMS`, is registered) |
| `AuditLogs`, `AggregatedAuditLogsList/Grid/View` | generated CRUD + custom aggregation page | Audit trail, with a rollup view |
| `ChangePassword`, `ResetPassword` | custom pages | Self-service password management |
| `WorkflowManager` | custom page + `_shared/workflow-manager/` | Approval workflow definitions (engine is shared infra, see §9) |
| `DocumentTypeManager`, `DocumentTypeEditor`, `DocumentAssetTypes`, `DocumentHeaderAssets`, `DocumentHeaderSlots` | custom pages + generated CRUD | Document/report header & type configuration |
| `FileManager`, `PageEditor` | custom pages | S3-backed file browser; ONLYOFFICE embedded editor |
| `IdGenerator` (`IDGenerator`) | custom page | Document/voucher ID-format configuration |
| `PsgcMap`, `Psgc` | custom page + generated CRUD | Philippine Standard Geographic Code reference + map UI |
| `EventPlayground`, `BullMqManager` | custom pages | Real-time event tester; background job queue inspector |
| `AiAssistant` | custom page | AI-assisted helper panel |
| `PermissionModuleCaptions` | custom page/table | Lets you rename a module/page row's display caption in the permission matrix without touching the generated `$USER_LEVEL_TABLES` registry |

### 8.2 Budgeting & Planning (`fims` schema)
The furthest-along business workflow in the repo — a Budget Request → Revision → Allocation cycle.

| Feature | Files | Backing table(s) | Purpose |
|---|---|---|---|
| Budget Request | `app/pages/budget-req-page.php` + `form/budget-req-form.php` + `form/budget-processing-form.php` + `readone/budget-req-readone.php` + `readone/budget-preview-readone.php` + `BudgetReqService.php` + `app/routes/budget_req.php` | `fims.budget_req`, `budget_req_program`, `budget_req_project`, `budget_line_items` + reference tables | The richest service in `app/` — not flat CRUD. One request (per fiscal year) holds nested Program → Project → COA-line-item blocks. `/budget_req/add` adds one Program-block (auto-creates the request header if absent); `/budget_req/program/update/{id}` replaces one Program-block. State machine: `submit-for-review` → `endorse` → `approve`/`return`, moving status `Draft → For Review → Endorsed → Approved`/`Returned`. `budget-processing-form.php` is the "Endorse" modal (Type of Fund + Component required per line, Suggested Amount optional). Role-gated: level 2 = Budget Officer (no Add/Edit/Delete), level 8 = Budget Head (no Add; sees Budget Preview) — the **only** page in `app/` with inline per-level UI gating |
| Budget Proposal | `app/pages/budget-prop-page.php` (+ the shared `form/budget-req-form.php` helpers, `form/budget-processing-form.php`, `readone/budget-preview-readone.php`) + `BudgetReqService.php` + `app/routes/fmd_budget_req.php` | `fims.budget_req` (+ its program / project / line-item tables) | The Financial Management Department's list of every Regional / District office's request: Submission Ref No., Region / Originating Unit, Submitted Date (`budget_req.submitted_at`), Amount, Status. Fetched from `GET /fmd/budget_req/list`, which fixes the scope server-side (FMD only, recognised by rc_code; else 403) — the page sends no RC parameters |
| Budget Revision | `app/pages/budget-revision-page.php` + `form/budget-rev-form.php` + `form/budget-rev-review.php` + `BudgetRevisionService.php` + `app/routes/budget_revision.php` | `fims.budget_req`, `budget_revision` | Revises an existing approved request; `budget-rev-review.php` is the approve/reject reviewer modal (`PUT /budget_revision/review/{id}`). **CSS bug — see §7.2** |
| Budget Allocation (CBA) | `form/budget-allocation-form.php` | — | **Leftover-namespace bug — see §7.3.** Client-side placeholder CBA-number generation (`'CBA#'.date('Y-m-d').rand(1,999)`), not a server-generated sequence like COA/RC use |
| Certificate of Budget (COB) | `app/pages/cob-page.php` | — | **CSS bug — see §7.2** |
| Memo Request | `app/pages/memo-req-page.php` | — | **Empty stub — see §7.5** |
| Fiscal Year | `FiscalYearService.php` + `app/routes/fiscal_year.php` | `fims.fiscal_year` (reads `budget_req`) | Read key is `{fiscal_yr}`, not `{id}`. Create/delete only, no update route. No dedicated page — feeds dropdowns on the budget pages |
| Expense Class | `ExpenseClassService.php` + `app/routes/expense_class.php` | `fims.expense_class` | Standard CRUD shape. No dedicated page — dropdown source |
| Component Type | `ComponentTypeService.php` + `app/routes/component_type.php` | `fims.component_type` | Standard CRUD shape. Used by the Budget Processing line-item form |
| Type of Fund | `TypeOfFundService.php` + `app/routes/type_of_fund.php` | `fims.type_of_fund` | Standard CRUD shape. Used by the Budget Processing line-item form |

### 8.3 Reference / Master Data
| Feature | Files | Backing table(s) | Purpose |
|---|---|---|---|
| Program | `app/pages/program-page.php` + `form/program-form.php` + `ProgramService.php` + `app/routes/program.php` | `fims.program`, `project` | Keyed by `{code}`. `GET /program/next-code` previews a server-generated code (`GlobalService::generateProgramCode()`) |
| Sub-Program | `app/pages/sub-program-page.php` + `form/sub-program-form.php` + `SubProgramService.php` + `app/routes/sub_program.php` | `fims.program`, `sub_program` | Keyed by `{code}`. `POST /sub_program/add-with-items` batch-creates multiple sub-programs under one program in one call |
| Project | `app/pages/project-page.php` + `form/project-form.php` + `ProjectService.php` + `app/routes/project.php` | `fims.program`, `project`, `sub_program` | Keyed by `{code}`. Service also exposes an internal (unrouted) `findOrCreateByName()` helper used by the budget-request add/update flow |
| Chart of Accounts | `app/pages/chart-of-accounts-page.php` + `form/chart-of-accounts-form.php` + `ChartOfAccountsService.php` + `app/routes/chart_of_accounts.php` | `fims.chart_of_accounts` | Hierarchical (parent/child), server-generated `ACC00001…` ids, soft-deletes via status toggle when referenced by PO items. Fully documented in `app/routes/README.md` |
| Responsibility Center | `app/pages/responsibility-center-page.php` + `form/responsibility-center-form.php` + `ResponsibilityCenterService.php` + `app/routes/responsibility_center.php` | `fims.responsibility_code` | Hierarchical, server-generated `RC-000…` ids. Fully documented in `app/routes/README.md` |

### 8.4 Procurement
| Feature | Files | Backing table(s) | Purpose |
|---|---|---|---|
| Purchase Order | `app/pages/po/page.php` (list) + `po/form.php` (add/edit modal, included by `page.php` — not its own route) + `po/readone.php` (detail, separate PHPMaker page `PurchaseOrderReadone`) + `PurchaseOrderService.php` + `app/routes/purchase-order.php` | `fims.purchase_orders`, `purchase_order_items` | Header comments say this was **ported from a `GLOBAL/` predecessor module and trimmed** to fit the `fims` schema (no PR linkage, no allocation/workflow engine, no payment workflow). Status machine `DRAFT → SUBMITTED → APPROVED/REJECTED → CANCELLED`; generates `PO-YYYY-MM-NNNN` numbers. Fully documented in `app/routes/README.md` |
| Vendors | `app/pages/vendors-page.php` + `form/vendor-form.php` + `VendorService.php` + `app/routes/vendor.php` | `fims.vendors`, `vendor_types` | Standard CRUD shape. Fully documented in `app/routes/README.md` |
| Inspection and Acceptance Report (IAR) | `app/pages/iar-module.php` (list) + `form/dr-module-form.php` (add/edit modal **and** the read-only "Actual Delivery" view modal, both included by the list page) + `PoDrService.php` + `app/routes/po_dr.php`; PHPMaker page `IarModule` | `fims.iar_module`, `fims.po_delivery_receipt` (`01 - table/25`) | Records what was delivered against a PO: **1 PO number → 1 IAR → many delivery receipt (DR) lines**. PO and DR numbers are free text (no FK to `purchase_orders` — there are no POs yet). Pattern B with a PHPMaker page shell. See the notes below the table |

**IAR module notes** (`PoDrService.php` header comment is the quick reference):

- **Addressing.** A PO is addressed by its `po_number` passed as `?po_number=…` (numbers may contain `/`
  or `-`), not by an id. Routes (all `->add($jwtMiddleware)`): `GET /po-dr/list`, `GET /po-dr/read`,
  `GET /po-dr/proforma`, `POST /po-dr/add`, `PUT /po-dr/update`, `DELETE /po-dr/delete`.
- **Header vs. lines.** `iar_module` holds `po_number`, the auto-generated `iar_number`
  (`IAR-YYYY-0001`, per-year counter) and the Sales Invoice # / date — entered **once per PO**, not per
  line. `po_delivery_receipt` holds one row per delivered item and carries the PO's `iar_number`.
  `po_number` is the enforced FK (`ON DELETE/UPDATE CASCADE`): deleting a PO's IAR row removes its lines.
- **Save payload.** `{po_number, si_number?, si_date?, lines:[{dr_line_id?, dr_number, delivery_date,
  account_code?, description, quantity, unit, unit_price, model_no?, serial_no?, property_no?, par_no?}]}`.
  Adding with an existing PO number appends lines to that PO (keeps its IAR number). On update, lines
  with a `dr_line_id` are updated, lines without are inserted, existing lines missing from the payload
  are deleted; the PO number may be renamed. Serial numbers are unique across all lines (swaps are
  handled by parking serials on temporary values inside the transaction).
- **Business rules (server-enforced in `PoDrService::clean()`).** Quantity is a whole number > 0;
  `amount = quantity × unit_price` is always recomputed server-side (the form's Amount field is
  read-only); a **Property & Equipment Category** (`account_code`, one of 10 fixed accounts) is required
  when the line **amount is 50,000 or more** and is discarded below that. The category list lives in two
  places that must be kept in sync: `PoDrService::ACCOUNTS` and `AMP_ACCOUNTS` in `dr-module-form.php`.
  Only the account **code** is stored; the form shows "code - name".
- **List page** columns: `# | PO Number | IAR Number | Sales Invoice # | PO Date | Requesting Office/Dept |
  Total Amount | Actions`. **PO Date and Requesting Office/Dept are front-end placeholders** (they render
  "—" — no service/data behind them yet). Actions: **View** (every item of the PO with its DR number),
  **Pro-forma**, Edit, Delete.
- **Pro-forma JEV** (`GET /po-dr/proforma`): one tab per delivery receipt, laid out like the accounting
  grid (Accounts and Explanation / SL Code / Subsidiary Ledger Dr-Cr / GL Acct Code / General Ledger
  Dr-Cr) with a Total Debit / Total Credit / Difference bar. Computed on the fly, **nothing is stored**:
  Dr `5-02-03-990` for lines with no category, Dr each used category account (summed per code), Cr
  `2-01-01-010` for the DR total; titles come from `fims.chart_of_accounts`. SL columns are empty and
  the **Create JEV** button is a placeholder (no JEV is written yet).
- **Install.** `01 - table/25 - fims_po_delivery_receipt.sql` creates both tables. If an earlier local
  version exists, drop `po_delivery_receipt` then `iar_module` first (the script is `IF NOT EXISTS`).
  Not browser- or DB-tested as of this writing (2026-11-10).

### 8.5 Platform utilities (not page-backed)
| Feature | Files | Purpose |
|---|---|---|
| Notifications | `app/routes/notifications.php` + `app/lib/NotificationManager.php` | Recent/count/mark-read/mark-all-read/send/test — no dedicated page in `app/`, consumed by the shared notification bell UI elsewhere in the framework |
| Swagger generation trigger | `app/routes/service.php` | `GET /Service` — generates OpenAPI docs via `ApiService` |
| Generic CRUD template | `app/routes/crud.php` + `app/services/crudServices.php` | **Dead code — see §7.4** |

> Full current API surface for `user-access`, `department-management`, `position-management`,
> `vendor`, `purchase-order`, `chart_of_accounts`, and `responsibility_center` routes is kept in
> **`app/routes/README.md`** — read it before calling or extending any of those endpoints; it's more
> current than this guide for exact HTTP verbs/paths.

---

## 9. Shared infrastructure (`_shared/`)

Cross-cutting code used by every page — coordinate before changing:

| Component | Path |
|---|---|
| Auth | `_shared/auth.php` |
| Page permissions | `_shared/PagePermission.php` (+ `_shared/README-PagePermission.md`) |
| Notifications | `_shared/notification.php` (+ `_shared/NOTIFICATION_README.md`) |
| Workflow engine | `_shared/workflow-manager/` |
| File manager | `_shared/file-manager/` |
| Document/editor | `_shared/document/` |
| Chat widget | `_shared/chat-widget/` |
| Job queue | `_shared/bullmq-manager/` |
| Swagger generation | `_shared/Swaggerize.php` |
| Branding / templates | `_shared/branding/`, `_shared/template/`, `_shared/nha-logos/` |

---

## 10. Running it locally

```bash
docker-compose up -d
# php-fpm + nginx (localhost:8090) + postgres (localhost:5433) + redis (6380) + minio (9020/9021) + adminer (8091)
```

First boot runs `composer install` automatically (named volume `fims-vendor`). Load the schema with
`install_system.js` (see caveats below) or by hand via `psql`/Adminer using the files under
`_assets/sql/` in folder-number order: `00 - public` → `00 - uac` → `01 - fims`.

`composer routes` must be re-run inside the container after adding/changing a `#[Map(...)]` route
attribute (§2).

---

## 11. Documentation index

| Document | Covers |
|---|---|
| **This file** | Current, verified architecture + module inventory |
| `_guidelines/page-creation-guidelines.md` | Page body patterns: data loading, UI components, forms/modals, error handling |
| `_guidelines/api-route-service-guidelines.md` | Service/route implementation, query patterns, middleware, transactions |
| `app/routes/README.md` | Authoritative per-endpoint reference for the routes it covers (see §8 note) |
| `_assets/sql/README.md` | SQL folder conventions — **partially stale**, see §13 |
| `system_docs/modules/*.docx` (also at `_assets/System Requirements/`) | Full target-scope SRS: Accounting Department, District, FMD, Regional, Treasury Department — the roadmap, not current state |
| `system_docs/ERD/` | Entity-relationship diagrams for `uac` + `fims` (rewritten 2026-11-09) — expand as schemas grow |
| `README-Docker.md`, `README-DEPLOY.md`, `README-installation.md` | Docker setup, on-prem deploy, project-wizard tooling |

---

## 12. Roadmap: what isn't built yet

Per the SRS docs in `system_docs/modules/`, the target system also covers **Accounting Department**
(Disbursement Control, Branch & Property, General Division — vouchers, JEVs, ledgers, payroll,
assets, receivables), **Treasury Department** (checks, cash position, FTLA, bonds, fund sweeping),
and **Regional/District offices** (field-office accounting cycle). None of that exists in this repo
yet — only User Access administration and the Financial Management Department's Budgeting/Reference/
Procurement slice (§8) have been built so far. When scoping new work, check the relevant SRS docx
first; don't assume prior-NHA-FIMS conventions (per-module folders, multi-schema `fmd`/`accounting`/
`treasury`/`regional` layout) apply here until they're actually implemented.

---

## 13. A note on other documentation in this repo

Several existing docs describe a **different, larger architecture** than what's actually on disk —
they were carried over from a predecessor NHA FIMS project as forward-looking reference material and
haven't been rewritten for this rebuild yet. Don't let them mislead you:

- **`README.md`** (root) describes per-module folders (`UAC/`, `FMD/`, `GSD/`, `ACCOUNTING-DCD/`,
  `ACCOUNTING-BAPD/`, `ACCOUNTING-GAD/`, `TREASURY/`, `REGIONAL/`, `LIBIS/`) with their own
  `composer.json`/`app/`/`src/` each. **None of these folders exist in this repo.** Everything is
  flat at the repo root (§3).
- **`_assets/sql/README.md`** describes 371 files across 9 schemas (`shared`, `uac`, `fmd`,
  `accounting`, `treasury`, `workflow`, `regional`, `district`, `integration`) including an
  `ACCOUNTING-BAPD/sql/` tree. The actual `_assets/sql/` tree today has **49 files** across two
  schemas (`uac`, `fims`) — see §3/§10. Use this guide's description, not that README's, until it's
  updated.
- **`install_system.js`** still contains logic referencing an `ACCOUNTING-BAPD/sql/` root that
  doesn't exist here — **verified 2026-11-09 that this is harmless**: the script checks
  `fs.pathExists()` before walking a root, so the missing folder just contributes zero files and
  doesn't error. A full dry run (all 49 files under `_assets/sql/`, in the exact order the script
  computes) on a throwaway database completed with **no errors** and produced the expected 30
  `uac` + 20 `fims` tables, with `superadmin` seeded correctly. So: the installer's file-discovery
  and execution logic is sound for this repo as-is — you do not need to fix it before using it.
  One piece of real (cosmetic) cruft found during that dry run: `00 - public/schema.sql` still runs
  `CREATE SCHEMA IF NOT EXISTS` for `fmd` and `workflow` — leftover from the old multi-schema plan.
  Both schemas get created empty (no tables land in them anywhere in this repo) — harmless, but
  worth dropping from `schema.sql` next time someone's in that file, so `\dn` doesn't show two dead
  schemas. Not tested in this pass: the interactive prompt flow itself (host/port/db-name questions,
  the composer-install step, and `--consolidated`/`--reset-only`/re-run idempotency) — only the SQL
  discovery-and-execution path was verified.
- **`_shared/system.php`** still lists the old multi-system roster (`UAC`, `FMD`, `ACCOUNTING-GAD`,
  `ACCOUNTING-BAPD`, `ACCOUNTING-PCDS`, `ACCOUNTING-DCD`, `TREASURY`, `REGIONAL`, `GSD`, `DOCUMENT`,
  `LIBIS`) and is read by `models/Login.php` to populate a "subsystem" dropdown on login. Only one
  system (`FIMS`) actually exists, so that dropdown currently lists mostly-dead entries — worth
  cleaning up when someone touches the login page.
- **`app/routes/crud.php`** + **`app/services/crudServices.php`** are declared under namespace
  `PHPMaker2024\FMD\Service` / `PHPMaker2024\FMD` (not `PHPMaker2024\FIMS`, like everything else in
  this repo) — a leftover from the `__BASE__` template this project was generated from. It still
  loads fine (PHP namespaces don't collide with the auto-discovery glob), but don't copy its
  namespace when creating a new service/route file — use `PHPMaker2024\FIMS`.

If you fix one of the above, update this section rather than deleting it outright, so the next
person knows what changed and when.

---

## 14. Where to start (new contributor)

1. `docker-compose up -d`, load the schema (§10), log in as `superadmin` / `superadmin123` (§5).
2. Read `_guidelines/page-creation-guidelines.md` and `_guidelines/api-route-service-guidelines.md`.
3. Pick one small, already-working feature and trace it end-to-end: `ResponsibilityCenterPage` is a
   good choice — page (`app/pages/responsibility-center-page.php`) → route
   (`app/routes/responsibility_center.php`) → service (`ResponsibilityCenterService.php`) → table
   (`fims.responsibility_code`).
4. Re-read §4 of this guide before building your first new page, so you pick the right pattern
   (generated CRUD vs. hand-built custom page vs. pure API+page) instead of over- or
   under-building.
