# SQL Schema Directory — FIMS NHA

> **Status:** rewritten 2026-11-09 to match what's actually in this folder and a verified dry-run
> install. The previous version of this file described a much larger, older architecture (371
> files, 9 schemas, an `ACCOUNTING-BAPD/sql/` tree, `npm run …` wrappers) that predates this repo's
> 2026-11-06 rebuild — none of that applies anymore. See
> `system_docs/developerguide/DEVELOPER-GUIDE.md` §11 for why these docs drift from the code.

This folder contains the SQL that builds the FIMS NHA database — **49 `.sql` files** across 3
module folders. Scripts are discovered and executed in natural (numeric-aware) path order by
`install_system.js` at the repo root.

```bash
node install_system.js             # interactive: SQL, composer install, or both
node install_system.js --sql-only  # SQL only, skip composer
```

There is **no `npm run …` wrapper today** — `package.json` at the repo root has no `"scripts"`
section. Ignore any doc (including older copies of this one) that tells you to run `npm run
install-system`, `npm run sql`, etc. — invoke `install_system.js` with `node` directly, as above.

---

## Folder Structure (as it exists today)

```
_assets/sql/
├── 00 - public/              2 files  — extension + schema setup, runs first
├── 00 - uac/                11 files  — User Access Control (uac schema)
│   ├── 01 - table/           3 files  — CREATE TABLE
│   ├── 02 - alter_table/     4 files  — ALTER TABLE
│   ├── 03 - views/           1 file   — CREATE VIEW
│   └── 04 - seed_data/       3 files  — INSERT seed/sample data
└── 01 - fims/                36 files  — budgeting/procurement/reference data (fims schema)
    ├── 01 - table/           19 files — CREATE TABLE
    ├── 02 - alter_table/      1 file   — ALTER TABLE (first batch, pre-seed)
    ├── 02 - sample_data/      5 files  — INSERT sample data
    ├── 02 - seed_data/        1 file   — INSERT seed data
    └── 03 - alter_table/     10 files — ALTER TABLE (second batch, added after 02-* already ran)
```

Unlike the old multi-schema FIMS project, folders here are organized **module-first, then
phase-type**, not phase-first-then-module. `00 - public` and `00 - uac` sort before `01 - fims`
because of the leading number; within each module folder, the phase-type subfolder decides order.

### Why `01 - fims` has two `alter_table` folders

`02 - alter_table` ran before `02 - sample_data`/`02 - seed_data` (so new columns exist before data
references them). `03 - alter_table` is a **separate, later batch** — changes added after seed data
already existed (FK additions, column renames/drops that depend on already-seeded rows, or simply
alterations written after the initial build). **When you add a new alter that must run after
existing data is in place, add it to (or create a new) `03 - alter_table`-style folder — don't
insert it into the already-executed `02 - alter_table`.** `.sql_run_history.json` (see below) only
tracks files by path, so inserting a new file into an old folder is harmless for history purposes,
but the *numbering convention* exists precisely so humans can tell at a glance which batch (relative
to data) a change belongs to — keep using it.

### Number reuse within a phase folder

Files inside a phase folder are numbered `01 - name.sql`, `02 - name.sql`, etc., but **this repo
does reuse the same number across sibling files** when they have no dependency on each other —
e.g. `_assets/sql/01 - fims/01 - table/05 - budget_rev.sql` and
`05 - fims_expense_class.sql` share `05`; ties break alphabetically on the rest of the filename.
This works as long as the two files genuinely don't depend on each other. If your new file *does*
depend on an earlier one in the same folder, give it the next free number — don't rely on the tie-
break order.

---

## How `install_system.js` Discovers and Orders Files

```js
sqlRoots = [
    path.join(process.cwd(), '_assets', 'sql'),
    path.join(process.cwd(), 'ACCOUNTING-BAPD', 'sql')   // does not exist in this repo
];
```

The second root is a leftover from the predecessor project. The script checks
`fs.pathExists()` before walking any root, so a missing folder just contributes zero files —
**verified harmless** by a full dry-run install (see "Verified" below).

- All `.sql` files are found recursively under each root, **excluding** any directory literally
  named `node_modules`, `.git`, `07 - cross-module-demo`, or `03 - afims` (case-insensitive). None
  of the last two currently exist in this repo — this exclusion list is forward-looking /
  carried over, not describing anything present today.
- Files are sorted with `localeCompare(..., { numeric: true })` — natural sort by full path, so
  `02 - alter_table` always sorts before `03 - alter_table`, and `9 - x.sql` sorts before
  `10 - x.sql`.
- Any file under a folder named `07 - schema_maintenance_structure` (or matching
  `NN - maintenance.sql`) is hoisted to run *after everything else*. **No file in this repo
  currently matches that pattern** — the hoist step is a no-op today, kept for when/if a
  maintenance folder is added.

---

## Running the Installer

```bash
node install_system.js                 # interactive: prompts for run mode, then Postgres connection
node install_system.js --sql-only      # SQL only, skip composer install
node install_system.js --composer-only # composer install only, skip SQL
node install_system.js --reset-only    # maintenance files only — currently a no-op (none exist)
node install_system.js --truncate-only # wipe transaction tables — currently throws, see caveat below
node install_system.js --consolidated  # currently throws — see caveat below
```

Without flags, it prompts for:
1. **Run mode**: all steps / SQL only / composer only / truncate only / cancel.
2. **Postgres connection**: host, port, username, password, database name. Defaults are leftover
   from an unrelated template (`port` defaults to `5432`, `database` defaults to
   `health_sync_db`) — if you're connecting to the Docker Compose Postgres from the host machine,
   type **port `5433`** (the mapped port in `docker-compose.yml`), and your real database name.
3. If the database doesn't already exist, it's created; if it does, you're offered a DROP + recreate
   (say no to keep your data).
4. **Run ALL files or only NEW ones** — "new" is determined by `.sql_run_history.json` at the repo
   root, which records every file path that ran successfully. Use *ALL* on a fresh database, *NEW*
   after pulling changes.

`ON_ERROR_STOP=1` is set for every `psql` invocation, so **one SQL error stops the whole run
immediately**, and the failed file is not recorded in history.

### Caveats on the less-common flags

- **`--consolidated`** requires `consolidate_sql.js` at the repo root. **That file does not exist
  in this repo** — using this flag throws immediately (`--consolidated requires consolidate_sql.js
  at repo root.`). The `_assets/sql-consolidated/` folder exists but is empty. Don't use this flag
  until that tooling is rebuilt for this repo (it existed in the predecessor project).
- **`--truncate-only`** reads
  `_assets/sql/07 - CROSS-MODULE-DEMO/01 - RESET_truncate_transactions.sql`, which **does not exist
  in this repo** (there is no cross-module demo dataset here yet). It fails with a clear
  `Truncate script not found: …` error rather than doing anything destructive — safe, just
  currently unusable.
- **`--reset-only`** runs only maintenance-folder files; since none exist yet, it prints "No
  maintenance SQL files found" and exits cleanly.

---

## Verified (2026-11-09)

A full dry run was done against a throwaway database: all 49 files, in the exact order
`install_system.js` computes, executed via `psql --set ON_ERROR_STOP=1` with **zero errors**,
producing 30 `uac` tables and 20 `fims` tables, with the `superadmin` seed user present and
correctly hashed. The missing `ACCOUNTING-BAPD/sql` fallback root was confirmed to be silently
skipped, not an error. Not covered by this pass: the interactive prompt flow itself, the
composer-install step, and `--reset-only`/`--consolidated`/`--truncate-only` (see caveats above).

---

## Database Schemas

Created by `00 - public/schema.sql`:

| Schema | Used by this repo? | Purpose |
|---|---|---|
| `uac` | Yes | User accounts, levels, permissions, audit logs, document/ID config |
| `fims` | Yes | Budget requests/revisions/allocations, chart of accounts, responsibility centers, programs/projects, vendors |
| `fmd` | **No tables land here** | Created by `schema.sql` but unused — leftover from the predecessor project's schema plan. Candidate for removal from `schema.sql`; confirm nothing depends on it existing before dropping. |
| `workflow` | **No tables land here** | Same as `fmd` — created but empty today. |
| `public` | Yes | `uuid-ossp` extension only |

---

## Where to Put New SQL

| What you're doing | Correct location |
|---|---|
| New table in `uac` | `00 - uac/01 - table/` |
| New table in `fims` | `01 - fims/01 - table/` |
| Column/constraint change that doesn't depend on existing data | `00 - uac/02 - alter_table/` or `01 - fims/02 - alter_table/` |
| Column/constraint change that depends on data already being seeded (FK add, rename after data exists) | `01 - fims/03 - alter_table/` (or the next unused top-level alter batch if you're past `03`) |
| New view | `00 - uac/03 - views/` (no `fims` views folder exists yet — create `01 - fims/0X - views/` if you need one, using the next free number) |
| Seed/sample data | `00 - uac/04 - seed_data/` or `01 - fims/02 - sample_data/` / `01 - fims/02 - seed_data/` (this repo doesn't distinguish "sample" vs "seed" strictly — both exist for `fims`; pick whichever existing folder your data resembles, or ask before inventing a third) |

Always use `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, and a preceding
`DROP VIEW IF EXISTS … CASCADE` before `CREATE VIEW`, so re-running the installer (or `--sql-only`
with "NEW" mode) is idempotent.

---

## Example: Adding a Column to an Existing Table

**Scenario:** add a `remarks` column to `fims.vendors`, where `vendors` already has
seed data referencing it.

1. Go to `01 - fims/03 - alter_table/` (the current highest batch is `08`, so use `09`).
2. Create `09 - vendors_add_remarks.sql`:
   ```sql
   ALTER TABLE fims.vendors
       ADD COLUMN IF NOT EXISTS remarks TEXT;
   ```
3. `node install_system.js --sql-only` → choose **Run only NEW SQL files**.

## Example: Creating a New Table

**Scenario:** create `fims.budget_allocation_history`.

1. Go to `01 - fims/01 - table/` (current highest is `15`, so use `16`).
2. Create `16 - fims_budget_allocation_history.sql` with `CREATE TABLE IF NOT EXISTS`.
3. `node install_system.js --sql-only`.

---

## Related docs

- `system_docs/developerguide/DEVELOPER-GUIDE.md` — overall architecture, conventions, and why
  several root-level docs (including the old version of this file) describe a different system
  than what's actually here.
- `.sql_run_history.json` (repo root) — per-file "already ran" ledger used by the installer's
  ALL vs NEW prompt. Don't hand-edit it; if you need to force a re-run, delete the relevant key(s).
