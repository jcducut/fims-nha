# FIMS NHA — Docker Setup (Local Dev Stack)

This repo ships a Docker Compose stack for local development: PHP-FPM, Nginx, PostgreSQL, Redis,
MinIO, EMQX, and Adminer all run as containers. You do not need PHP, PostgreSQL, Redis, MinIO, or an MQTT broker
installed natively on Windows to run the app — Docker provides them.

> This is a **flat, single-project** repo (`PHPMaker2024\FIMS`) — there's no per-module
> `composer install` loop. One `composer.json` at the repo root, installed once. See
> `system_docs/developerguide/DEVELOPER-GUIDE.md` for the app architecture.

## What's inside

| Service    | Container name      | Image                   | Host port(s)     | Purpose |
|------------|----------------------|--------------------------|------------------|---------|
| `nginx`    | `fims-nha-nginx`      | `nginx:alpine`           | `8090`           | Web server (entry point) |
| `php`      | `fims-nha-php`        | built from `Dockerfile`  | *(internal 9000)* | PHP 8.2-FPM running the app |
| `postgres` | `fims-nha-postgres`   | `postgres:16-alpine`     | `5433` → 5432    | Database `fims` (empty until SQL is loaded) |
| `redis`    | `fims-nha-redis`      | `redis:7-alpine`         | `6380` → 6379    | Cache / BullMQ queues |
| `minio`    | `fims-nha-minio`      | `minio/minio`            | `9020` (API) / `9021` (console) | S3-compatible object storage |
| `emqx`     | `fims-nha-emqx`       | `emqx/emqx` (latest)    | `1883` MQTT, `8083` WebSocket, `8084`/`8883` TLS, `18083` dashboard | MQTT broker for real-time notification push |
| `adminer`  | `fims-nha-adminer`    | `adminer`                | `8091`           | Web-based DB browser |

Host ports on the left are **not** the containers' internal ports — e.g. Postgres listens on 5432
inside its container, but is reached at `localhost:5433` from Windows (5432 is reserved for a
natively-installed Postgres — see the gotcha below).

Named volumes persist data between restarts: `fims-postgres-data`, `fims-redis-data`,
`fims-minio-data`, `fims-emqx-data`, `fims-emqx-log`, `fims-vendor` (the last one holds `vendor/` on a Docker-native filesystem for
speed — it shadows whatever `vendor/` exists on the Windows-mounted bind).

## ⚠️ Important: which Postgres does the app actually use?

Check `.env` before assuming "load SQL into the `postgres` container" is enough:

```
DB_HOST=host.docker.internal
DB_PORT=5432
DB_NAME=fims
```

`host.docker.internal:5432` resolves, from inside the `php` container, to port **5432 on the
Windows host** — that is **not** the same thing as the dockerized `postgres` service, which is
mapped to **host port 5433** (`"5433:5432"` in `docker-compose.yml`). In other words, as checked
in today, the app talks to a **natively-installed Postgres on the Windows host**, and the
`fims-nha-postgres` container sits there unused (schema never loaded into it).

If you want the app to use the containerized Postgres instead (recommended for a clean,
self-contained setup):

```ini
# .env
DB_HOST=postgres     # the Docker Compose service name, not host.docker.internal
DB_PORT=5432         # Postgres's port *inside* its own container/network, not the 5433 host mapping
```

Then load the schema into that container (see below) rather than into whatever is listening on the
Windows host's port 5432.

Similarly, uploads currently go to **DigitalOcean Spaces** (`DO_SPACES_*` / `UPLOAD_DEST_PATH` in
`.env`), not the local `minio` container — MinIO runs in this stack but isn't the active upload
target unless you repoint `UPLOAD_DEST_PATH` at it.

## Prerequisites

- **Docker Desktop for Windows** (WSL2 backend recommended)
- ~4 GB free disk space for images and volumes

## Quick start

```bash
# From the repo root
docker-compose up -d
```

First run downloads the Postgres/Redis/MinIO/Adminer images, builds the PHP image, and runs
`composer install` automatically (see the `php` service's bootstrap command in
`docker-compose.yml`). Takes a few minutes. Subsequent starts are fast.

Use `--build` only after changing `Dockerfile`:

```bash
docker-compose up -d --build
```

### Access points

| URL | What |
|---|---|
| http://localhost:8090/ | Main app |
| http://localhost:9021/ | MinIO web console |
| http://localhost:8091/ | Adminer (DB browser) |
| http://localhost:18083/ | EMQX dashboard (default login `admin` / `public`; you must change it on first login) |

MinIO console login: `s3phatneglo-archive` / `0yq5h3to9`.
Adminer: System `PostgreSQL`, Server `postgres`, User `postgres`, Password `123456`, DB `fims`
(this reaches the dockerized Postgres on 5433, not whatever `.env`'s `DB_HOST` points the app at).

## EMQX (MQTT broker for notifications)

The notification bell (`_shared/notification.php`) polls `/api/notifications/recent` every 30 s, so it
works with or without EMQX. EMQX only adds instant push on top of that.

- **Start:** `docker-compose up -d emqx` (it is also started by `docker-compose up -d`).
- **Ports:** `1883` MQTT, `8083` MQTT over WebSocket (`ws://localhost:8083/mqtt`, used by browsers),
  `8084`/`8883` TLS, `18083` dashboard and REST API.
- **API key and secret:** open http://localhost:18083, then **System → API Keys → New**. Put the values in
  `.env`:

  ```ini
  EMQX_API_URL=http://emqx:18083        # from inside the php container (service name)
  EMQX_API_KEY=<key from the dashboard>
  EMQX_API_SECRET=<secret from the dashboard>
  ```

  `src/config.development.php` reads these as `Config('EMQX.API_URL' / 'API_KEY' / 'API_SECRET')`.
  Use `http://localhost:18083` if the app runs outside Docker. The port is the dashboard/REST port
  (`18083`), not the MQTT port `1883`.
- **Data:** kept in the `fims-emqx-data` and `fims-emqx-log` volumes. `docker-compose down -v` wipes them.
- **Version:** defaults to `latest` (6.3.2 Enterprise at the time of writing). To pin a release, change the `image:` line in `docker-compose.yml` to a specific tag, e.g. `emqx/emqx:6.3.2`.
- **Auth:** the broker uses EMQX's built-in defaults (no external auth hook). Add authentication and ACLs in
  the dashboard before exposing it beyond your machine.
- **Port conflict:** if you already run another EMQX container (for example `emqx-emqx-1` from
  `D:\tools\Docker-Dev-Setup\emqx`), stop it first (`docker stop emqx-emqx-1`) or the ports `1883`,
  `8083` and `18083` will clash.
- **Dockerfile:** unchanged. EMQX is its own image, so nothing needs to be installed in the PHP image;
  the app talks to it over HTTP and WebSocket.

## Initializing the database

Whichever Postgres `.env`'s `DB_HOST`/`DB_PORT` resolve to (see the gotcha above) comes up
**empty** — you still need to load the schema.

### Load the schema via the project's installer (recommended)

Run this **inside the `php` container**, not on the Windows host — `host.docker.internal` (and the
container's view of `.env`) only resolves correctly from inside Docker's network:

```bash
docker-compose exec php node install_system.js --sql-only
```

This loads the ~49 files under `_assets/sql/` in folder order (`00 - public` → `00 - uac` →
`01 - fims`). See `_assets/sql/README.md` for what each folder contains.

### Restore from an existing dump

```bash
docker-compose exec -T postgres psql -U postgres -d fims < your_dump.sql
```

(This loads into the **dockerized** Postgres on 5433 regardless of what `.env` points the app at —
adjust `DB_HOST`/`DB_PORT` to match if you want the app to see this data.)

> There is no consolidated single-file SQL build checked into this repo right now
> (`_assets/sql-consolidated/` exists but is empty, and the `consolidate_sql.js` script it depends
> on isn't present) — use the installer above, not a hand-picked file from that folder.

## Common commands

```bash
# Start / stop
docker-compose up -d
docker-compose down

# Rebuild after changing Dockerfile
docker-compose up -d --build

# Logs (all services, follow)
docker-compose logs -f

# Just PHP or just Nginx logs
docker-compose logs -f php
docker-compose logs -f nginx

# Shell inside PHP container
docker-compose exec php bash

# Run psql inside the DB container (dockerized Postgres, port 5433 on host)
docker-compose exec postgres psql -U postgres -d fims

# Regenerate the PHPMaker attribute route cache after adding/changing a #[Map(...)] route
docker-compose exec php sh -lc "composer routes"
```

## Running host-side scripts (Node, etc.)

Scripts like `install_system.js` can run either inside the `php` container or on the Windows host.
Whether a host-side run can reach the database depends entirely on what `.env`'s `DB_HOST` is set
to:

- `DB_HOST=host.docker.internal` (checked-in default) — this hostname is meant to be resolved
  **from inside a container** to reach the host machine. Running the script directly on Windows
  will generally **not** resolve it (there's no such entry in the Windows hosts file by default).
  Run these scripts with `docker-compose exec php ...` instead.
- `DB_HOST=postgres` (if you repoint it per the gotcha above) — resolves to the dockerized Postgres
  from *inside* the Docker network only. A host-side script would need `DB_HOST=localhost` and
  `DB_PORT=5433` (the host-mapped port) to reach the same container from outside Docker.

## Wiping and starting fresh

```bash
docker-compose down -v          # -v removes the data volumes too
docker-compose up -d --build
```

**Warning:** `-v` deletes the dockerized Postgres, Redis, and MinIO data (not whatever Postgres
`host.docker.internal` happens to point at, if you're using the checked-in default).

## Troubleshooting

**Port already in use (8090/8091/5433/6380/9020/9021/1883/8083/18083)**
Something on Windows already listens on that port. Stop it, or change the host-side port mapping in
`docker-compose.yml` (left side of each `"host:container"` pair).

**`composer install` failed**
Run it manually to see the error:
```bash
docker-compose exec php bash -c "composer install -vvv"
```

**File permission issues in `temp_uploads/`**
```bash
docker-compose exec php chown -R www-data:www-data /var/www/html/temp_uploads
```

**Can't connect to DB from PHP**
First confirm which Postgres `.env` actually points at (see the gotcha above), then:
```bash
docker-compose ps
docker-compose logs postgres
```

## File structure

```
Dockerfile              PHP 8.2-FPM image: pdo_pgsql, redis, imagick, gd, ldap, intl, + Node.js 20
                         (for DOCX generation scripts) and pdftk (PDF form-filling)
docker-compose.yml       Full dev stack: php, nginx, postgres, redis, minio, emqx, adminer
nginx.conf               Nginx routing — serves app/pages/login.php at "/", proxies /api/ to the
                         Slim API, denies access to vendor/, composer.*, and .env
_docker/php/php.ini      PHP config overrides (opcache.validate_timestamps=1, 2s revalidate)
_docker/php/www.conf     PHP-FPM pool config
.env                     Runtime config (see the gotcha above re: DB_HOST)
.dockerignore            Files excluded from the PHP image build context
```
