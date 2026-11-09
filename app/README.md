# `app/` — Unified Coding Structure

This folder is where almost all hand-written application code lives: pages, API services, API
routes, and small shared helpers. Everything else at the repo root (`controllers/`, `models/`,
`views/`, `src/`) is PHPMaker-generated scaffolding that wraps around what's in here.

> This repo is **one flat project** (`PHPMaker2024\FIMS` namespace) — there is no per-module folder
> split like `UAC/`, `FMD/`, `ACCOUNTING-*/` seen in older NHA FIMS projects. One `app/` folder
> serves every feature. For the full architecture (two front controllers, PHPMaker attribute
> routing, DB schemas, etc.) see `system_docs/developerguide/DEVELOPER-GUIDE.md` — this README only
> covers what's inside `app/` and how to extend it.

## Folder map

```
app/
├── api.php              # Slim API bootstrap — JWT/access middleware, auto-discovery of services + routes
├── global.php            # App config / DB connection bootstrap; manually includes app/lib/*
├── global_script.php      # Opt-in shared HTML/JS partial (currently empty)
├── preloader.php          # Full-page loading-overlay markup/JS (included globally)
├── lib/                   # Hand-written helpers NOT auto-discovered — wired by hand in global.php/api.php
├── pages/                 # Custom page bodies (HTML + PHP + JS), one file per feature
│   ├── form/              #   create/edit form fragments, included by their parent page
│   ├── readone/            #   detail/view fragments
│   └── image-logos/        #   static bank/agency logos used by printed forms
├── routes/                # Slim route files — auto-discovered, see below
│   └── README.md           # Authoritative per-endpoint reference (HTTP verbs, paths, params)
└── services/               # Business-logic service classes — auto-discovered, see below
```

## The two wiring generations

| | `app/services/` + `app/routes/` | `app/lib/` |
|---|---|---|
| Discovery | **Auto-discovered.** `app/api.php` globs every `*.php` file in both folders (skipping files starting with `_`) and `require_once`s it. | **Manual.** `app/global.php` and `app/api.php` `include_once`/`require_once` each file by name. |
| When to use | **Default choice for new work.** Drop the file in and it loads — nothing else to wire. | Only if you're extending one of the existing files there, or need something loaded before the DB/session bootstrap in `global.php` runs. |
| Gotcha | Files starting with `_` are skipped on purpose (e.g. a shared helper not meant to be auto-run as a route file). | A new file here does **nothing** until you add an `include_once` line yourself — it's easy to add a file and wonder why it 404s. |

```php
// app/api.php (excerpt)
foreach (glob($appBase . 'services/[!_]*.php') ?: [] as $f) { require_once $f; }
foreach (glob($appBase . 'routes/[!_]*.php') ?: [] as $f) { require_once $f; }
```

## Namespace prefix on data helpers

The same query helpers (`ExecuteRow`, `ExecuteRows`, `ExecuteScalar`, `Execute`, `QuotedValue`,
`DataType`) are called differently depending on which folder you're in:

| Where | Prefix | Example |
|---|---|---|
| `app/services/*.php`, `app/lib/*.php` | `App\` | `App\ExecuteRow(...)` |
| `app/pages/*.php` (custom page bodies) | none | `ExecuteRow(...)` |
| `app/routes/*.php` (API route closures) | none | `ExecuteRows(...)` |

Always quote SQL parameters — never concatenate raw user input:

```php
$sql = "SELECT * FROM fims.vendors WHERE vendor_code = " .
    QuotedValue($code, DataType::STRING, "DB");
```

## How a typical feature is built (the `app/` slice)

Most business pages follow the same three-file shape inside `app/` (the full recipe, including the
`models/`/`views/`/`controllers/` scaffolding outside `app/`, is in
`system_docs/developerguide/DEVELOPER-GUIDE.md` §4):

1. **Service** — `app/services/<Feature>Service.php`. Plain class, methods like `getAll()`,
   `create($data)`, using the `App\`-prefixed query helpers above.
2. **Route** — `app/routes/<feature>.php`. Slim routes (`$app->get(...)`, `$app->post(...)`, …)
   chained with `->add($accessMiddleware)->add($jwtMiddleware)`. No manual wiring — auto-discovered.
3. **Page body** — `app/pages/<feature>-page.php`. Plain PHP + HTML + JS. Reads
   `CurrentUserLevel()` for role-based UI gating, fetches data client-side with
   `'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN` against the routes above (reached externally
   under the `/api` prefix, e.g. a route declared `/vendors/all` is `GET /api/vendors/all`).

Most routes follow one uniform REST shape (deviations are documented per-file in
`app/routes/README.md`):

```
GET    /{feature}/all          — unpaginated, for dropdowns
GET    /{feature}/list         — paginated (page, limit) + filters, usually with a `summary` block
GET    /{feature}/read/{id}    — one record
POST   /{feature}/add          — create
PUT    /{feature}/update/{id}  — update
DELETE /{feature}/delete/{id}  — delete (often blocked if referenced elsewhere — soft-delete via status toggle instead)
```

Some features are **pure API + page**, with no PHPMaker page model at all (e.g. `Vendors`) — use
this lighter form when the page is fully self-contained.

## Adding a new feature — checklist

1. Create `app/services/<Feature>Service.php` (or add to an existing one if it's a small
   addition to a related feature).
2. Create `app/routes/<feature>.php`. Just drop it in — no manual include anywhere.
3. Create `app/pages/<feature>-page.php` (+ `form/`, `readone/` fragments if it needs an
   add/edit modal or detail view).
4. If the page needs its own PHPMaker-side route (`/YourPage`), also wire the
   `models/`/`views/`/`controllers/` + `src/definitions.php` + `views/menu.php` +
   `src/userlevelsettings.php` steps described in the Developer Guide §4, then run
   `composer routes` (`docker exec fims-nha-php sh -lc "composer routes"`) to refresh the
   attribute route cache.
5. Document the new endpoints in `app/routes/README.md`.

## Known gotchas in this folder

- **`app/lib/` is not auto-discovered.** If you add a file there, it silently does nothing until
  you add an `include_once` in `global.php` (or `api.php`, as `GlobalService.php` is).
- **`app/routes/crud.php` + `app/services/crudServices.php` are dead code** — leftover from the
  project template, declared under a `PHPMaker2024\FMD` namespace that doesn't match the rest of
  this repo (`PHPMaker2024\FIMS`) and pointed at a `shared.programs` table/schema that doesn't
  exist. Don't copy their namespace into new files.
- **`app/pages/air-module.php` and `app/pages/memo-req-page.php` are empty stubs** (zero-byte
  files, wired into the menu but not implemented yet).
- Two pages (`cob-page.php`, `budget-revision-page.php`) link a stylesheet path
  (`/css/nha-fims.css`) that doesn't exist — the real path is `_shared/css/nha-fims.css`.

See `system_docs/developerguide/DEVELOPER-GUIDE.md` §7 for the full, up-to-date list with details.

## Further reading

| Document | Covers |
|---|---|
| `system_docs/developerguide/DEVELOPER-GUIDE.md` | Full architecture: two front controllers, PHPMaker attribute routing, DB schemas, permissions, feature-by-feature inventory |
| `app/routes/README.md` | Authoritative per-endpoint reference (HTTP verbs, paths, params) for every route file |
| `_guidelines/page-creation-guidelines.md` | Page body patterns: data loading, modals, DataTables wiring |
| `_guidelines/api-route-service-guidelines.md` | Service/route implementation, query patterns, middleware, transactions |
