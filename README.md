# FIMS NHA — Financial Information Management System

A financial system for the **National Housing Authority (NHA)**, also referred to internally as
**AFIMS** (Accounting and Financial Information Management System).

> **This checkout is a from-scratch rebuild**, started 2026-11-06, branch `documents`. It is a
> single, unified PHPMaker 2024 + Slim 4 project named `FIMS` — **one flat project**, not a
> per-module folder split (`UAC/`, `FMD/`, `ACCOUNTING-*/`, `TREASURY/`, `REGIONAL/`, …) like older
> NHA FIMS codebases. If you've worked on a predecessor project, drop that mental model here.
>
> **The authoritative, up-to-date reference is
> [`system_docs/developerguide/DEVELOPER-GUIDE.md`](system_docs/developerguide/DEVELOPER-GUIDE.md).**
> This README is a short orientation; the Developer Guide has the full architecture, conventions,
> known issues, and feature-by-feature inventory — read it before building anything non-trivial.

---

## What's built so far

This repo currently implements a slice of NHA's full target financial system:

- **User Access & Administration** (`uac` schema) — login/session, users, roles, permissions,
  departments, positions, document/header config, file manager, workflow engine, audit logs.
- **Financial Management Department — Budgeting & Procurement** (`fims` schema) — Budget Request →
  Revision → Allocation cycle, Chart of Accounts, Responsibility Centers, Programs/Sub-Programs/
  Projects, Vendors, Purchase Orders.

The full target scope (Accounting Department, Treasury Department, Regional/District offices) is
specified in the SRS documents under `system_docs/modules/*.docx` — treat those as the **roadmap**,
not current state. See Developer Guide §12 for the gap.

---

## Architecture at a glance

| Layer | Technology |
|---|---|
| Language | PHP 8.1+ |
| Code generator | PHPMaker 2024 (`controllers/`, `models/`, `views/`) |
| Web framework | Slim 4 (routing, middleware, DI container) |
| Database | PostgreSQL — schemas `uac` and `fims` |
| Auth | JWT (`firebase/php-jwt`), cookie + bearer token |
| Frontend | Bootstrap 5, AdminLTE 3, jQuery, DataTables, SweetAlert2 |
| Containerization | Docker Compose |
| Reverse proxy | Nginx, app served on `http://localhost:8090` |

Two front controllers share the same framework code and DI container: `index.php` (pages, base path
`/`) and `api/index.php` (a separate Slim app, base path `/api` — every route in `app/routes/*.php`
is reached externally with an `/api` prefix). Full detail in Developer Guide §2.

---

## Directory map

```
fims-nha/
├── app/                  # Almost all hand-written code: pages, API services, API routes
│   ├── README.md          #   folder-local conventions (auto-discovery, namespace prefixes)
│   ├── pages/              # Custom page bodies (form/, readone/, po/ subfolders)
│   ├── routes/              # Slim route files (auto-discovered) + README.md (endpoint reference)
│   ├── services/             # Business-logic service classes (auto-discovered)
│   └── lib/                   # Hand-written helpers wired by hand (not auto-discovered)
├── controllers/          # PHPMaker-generated controllers
├── models/                # PHPMaker-generated page/table models
├── views/                 # PHPMaker-generated view wrappers
├── src/                    # Framework: DI definitions, attributes, middleware, permissions
├── _shared/                 # Cross-cutting: auth, page permissions, workflow engine, notifications,
│                             # file manager, document/editor, chat widget, job queue
├── _guidelines/              # House style guides — read before writing new pages/routes
├── _assets/sql/                # SQL source tree (schema + seed data), see its own README.md
├── system_docs/                 # Developer Guide, ERD notes, module SRS docs
├── install_system.js              # SQL + composer installer
├── docker-compose.yml               # php / nginx / postgres / redis / minio / adminer
└── composer.json                      # `composer routes` regenerates the attribute route cache
```

Don't hand-edit `controllers/`, `models/`, or `views/` unless you know PHPMaker's generation model —
they wrap around what's in `app/`.

---

## Setup

### Prerequisites
- Docker + Docker Compose (recommended path)
- Alternatively, locally: PHP 8.1+, PostgreSQL 14+, Composer 2+, Node.js 18+

### Run with Docker

```bash
docker-compose up -d
```

First boot runs `composer install` automatically. This brings up:

| Service | URL | Notes |
|---|---|---|
| App (nginx) | http://localhost:8090 | Main entry point |
| PostgreSQL | localhost:5433 | db `fims`, user `postgres` / `123456` |
| Redis | localhost:6380 | |
| MinIO (S3 console) | http://localhost:9021 | API on 9020 |
| Adminer | http://localhost:8091 | DB browser |

Load the schema (empty until this runs):

```bash
node install_system.js             # interactive: SQL, composer install, or both
node install_system.js --sql-only  # SQL only
```

This executes the ~49 files under `_assets/sql/` in folder-number order (`00 - public` →
`00 - uac` → `01 - fims`). See `_assets/sql/README.md` for the folder layout.

After adding or changing a `#[Map(...)]` route attribute on a controller, regenerate the route
cache:

```bash
docker exec fims-nha-php sh -lc "composer routes"
```

### Default login

`superadmin` / `superadmin123` — seeded by
`_assets/sql/00 - uac/04 - seed_data/01 - superadmin_user.sql`. Passwords are stored case-folded
(hash is of the lowercased password).

---

## Documentation

| Document | Covers |
|---|---|
| **[`system_docs/developerguide/DEVELOPER-GUIDE.md`](system_docs/developerguide/DEVELOPER-GUIDE.md)** | **Start here.** Full architecture, conventions, known issues, feature-by-feature inventory |
| `app/README.md` | Conventions inside `app/` (auto-discovery, namespace prefixes, adding a feature) |
| `app/routes/README.md` | Authoritative per-endpoint reference for the routes it covers |
| `_guidelines/page-creation-guidelines.md` | Page body patterns: data loading, modals, DataTables wiring |
| `_guidelines/api-route-service-guidelines.md` | Service/route implementation, query patterns, middleware, transactions |
| `_assets/sql/README.md` | SQL folder conventions and `install_system.js` usage |
| `system_docs/ERD/` | Entity-relationship diagrams for `uac` + `fims` |
| `system_docs/modules/*.docx` | Full target-scope SRS (roadmap, not current state) |
| `README-Docker.md` | Local dev Docker stack — ports, container names, the `DB_HOST` gotcha (app talks to a native host Postgres by default, not the dockerized one) |
| `README-DEPLOY.md` | On-prem deployment — honestly flags what's real (the dev stack) vs. not yet built (TLS, backups, CI/CD) |
| `README-installation.md` | Project-wizard tooling |

---

## Where to start (new contributor)

1. `docker-compose up -d`, load the schema, log in as `superadmin` / `pass`.
2. Read `_guidelines/page-creation-guidelines.md` and `_guidelines/api-route-service-guidelines.md`.
3. Read **Developer Guide §4** to understand the two ways a feature gets built here (PHPMaker-
   generated CRUD vs. hand-built custom page), then trace one working feature end-to-end —
   `ResponsibilityCenterPage` is a good choice: page → route → service → table.
4. Check Developer Guide §7 and §13 before touching anything that looks odd — several known,
   already-diagnosed issues and stale-doc traps are documented there.
