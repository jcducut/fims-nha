# FIMS NHA — Production Deployment Guide

Target audience: whoever administers the NHA server. No prior Docker knowledge assumed.

> **Status check (2026-11-09): this repo has no production-specific deployment stack today.**
> Earlier versions of this guide described a `docker-compose.prod.yml`, `Dockerfile.prod`,
> `.env.prod.example`, a `deploy.sh`, a Caddy reverse-proxy container, and a `backup` sidecar — **none
> of those files exist in this repo.** There is also no `.github/workflows/deploy.yml` — this repo
> has no `.github/` folder at all, so there's no CI/CD pipeline wired up yet either.
>
> What *does* exist is the single dev-oriented stack described in **`README-Docker.md`**
> (`docker-compose.yml` + `Dockerfile` + `nginx.conf`) — plain HTTP on port 8090, no TLS
> termination, no backup automation, no process supervision beyond Docker's own restart policy.
> It's usable as a starting point for an on-prem server, but the production-hardening pieces below
> (reverse proxy + TLS, scheduled backups, secrets management, CI/CD) need to be built — this guide
> now documents what's real and flags what's still a TODO, instead of describing a stack that isn't
> there.

---

## 1. Server prerequisites

| Resource       | Minimum | Recommended       |
|----------------|---------|-------------------|
| CPU            | 4 cores | 8 cores           |
| RAM            | 8 GB    | 16 GB             |
| System disk    | 100 GB SSD | 200 GB SSD     |
| Data disk (DB) | 200 GB SSD | 500 GB SSD     |
| OS             | Ubuntu 22.04 LTS | Ubuntu 22.04 LTS |

### Install Docker + Compose

```bash
# On the server as root or with sudo:
curl -fsSL https://get.docker.com | sh
apt-get install -y docker-compose-plugin git

# Verify:
docker --version
docker compose version
```

### Reverse proxy / TLS — not yet included in this repo

`nginx.conf` here serves plain HTTP on container port 80 (mapped to host `8090` in
`docker-compose.yml`) with no TLS directives. For an internet- or LAN-facing deployment you need to
put something in front of it yourself — e.g. a host-level Nginx/Caddy with a Let's Encrypt
certificate, proxying to `localhost:8090`, or add a `caddy`/`traefik` service to a copy of
`docker-compose.yml`. Until that's built and checked in, treat this stack as **internal-network
only**: don't expose port 8090 directly to the public internet.

```bash
# If/when you do put a reverse proxy in front with public TLS:
ufw allow 80/tcp
ufw allow 443/tcp
ufw reload
```

---

## 2. First-time deployment (using the stack that actually exists today)

```bash
# 1. Clone the repo
cd /opt
git clone <repo-url> fims-nha
cd fims-nha

# 2. Create the env file
cp .env.example .env
nano .env
#    At minimum, set for a server deployment (not the checked-in dev defaults):
#    - DB_HOST / DB_PORT / DB_NAME / DB_PASSWORD — see the DB_HOST gotcha in README-Docker.md;
#      for a server you almost certainly want DB_HOST=postgres (the Compose service), not
#      host.docker.internal, unless you're intentionally pointing at a Postgres already running
#      on the host.
#    - JWT secret(s), DO_SPACES_* (or point UPLOAD_DEST_PATH elsewhere), ONLYOFFICE_* if used,
#      any third-party API keys (PIMS, LIBIS, etc.) relevant to this deployment.

# 3. Bring the stack up
docker compose up -d --build

# 4. Load the FIMS schema (first time only) — see section 3 below.
```

Open `http://<server-ip>:8090/` (or your reverse-proxy's URL, once one exists) in a browser.

---

## 3. Load the database schema

First-time only, from the project directory, run **inside the `php` container** (see
README-Docker.md for why `host.docker.internal`/`.env` resolution matters here):

```bash
docker compose exec php node install_system.js --sql-only
```

This runs the ~49 files under `_assets/sql/` in folder order. There is no pre-baked `schema.sql`
mounted into `/docker-entrypoint-initdb.d` — the installer above is the supported path.

If you have a real DB dump from staging/prod, restore that instead:

```bash
gunzip -c backup.sql.gz | \
  docker compose exec -T postgres psql -U postgres -d fims
```

---

## 4. Regular deployments

There is no `deploy.sh` or CI/CD pipeline in this repo yet. Until one is built, a manual redeploy
looks like:

```bash
cd /opt/fims-nha
git pull
docker compose up -d --build
docker compose exec php sh -lc "composer routes"   # if any #[Map(...)] routes changed
docker compose ps
```

If you want a one-command script, a minimal starting point (not checked in — write this yourself
and keep it out of git, since it'll have server-specific paths):

```bash
#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
[[ "${1:-}" != "--no-pull" ]] && git pull
docker compose up -d --build
docker compose exec php sh -lc "composer routes"
docker compose ps
```

---

## 5. Backups

**Not automated in this repo today.** There is no `backup` sidecar container. Take manual Postgres
dumps until scheduled backups are built:

```bash
docker compose exec -T postgres pg_dump -U postgres -d fims | gzip > "fims_$(date +%F).sql.gz"
```

Restore:
```bash
gunzip -c fims_<date>.sql.gz | docker compose exec -T postgres psql -U postgres -d fims
```

A reasonable next step is a `cron` entry on the host running the `pg_dump` command above nightly,
or a dedicated backup sidecar container (as earlier drafts of this guide assumed existed) — neither
is built yet.

---

## 6. Monitoring

```bash
docker compose ps
docker compose logs -f
docker compose logs -f php
docker system df
```

---

## 7. Troubleshooting

**PHP shows 502 Bad Gateway**
- PHP container likely crashed or isn't ready yet. Check: `docker compose logs --tail=100 php`
- Verify DB is healthy: `docker compose ps`

**"Database connection failed"**
- Confirm `.env`'s `DB_HOST`/`DB_PORT`/`DB_PASSWORD`/`DB_NAME` actually point at a reachable,
  schema-loaded Postgres — see the gotcha in README-Docker.md. `DB_NAME` for this repo is `fims`,
  not any older project's database name.
- `docker compose exec postgres psql -U postgres -l` should list `fims`.

**Out of memory / slow**
- `docker stats` to see per-container usage.
- Tune `_docker/php/www.conf` (`pm.max_children` etc.) if PHP is saturated.

---

## 8. Rollback

```bash
git log --oneline -10
git checkout <previous-commit-sha>
docker compose up -d --build
docker compose exec php sh -lc "composer routes"

# If the DB was migrated, restore the pre-deploy dump:
gunzip -c fims_<date>.sql.gz | docker compose exec -T postgres psql -U postgres -d fims
```

---

## 9. Stopping / uninstalling

```bash
# Stop but keep data
docker compose down

# WIPE EVERYTHING including the database and uploads
docker compose down -v
```

---

## What's actually in this repo vs. what still needs building

| Piece | Status |
|---|---|
| Dev/server Docker stack (`docker-compose.yml`, `Dockerfile`, `nginx.conf`) | **Exists** — see `README-Docker.md` |
| Production-specific compose file, multi-stage prod `Dockerfile`, `.env.prod` template | **Does not exist** |
| Reverse proxy + TLS (Caddy/Nginx+certbot) | **Does not exist** — plain HTTP only today |
| Scheduled DB backups / sidecar | **Does not exist** — manual `pg_dump` only |
| `deploy.sh` / one-command redeploy | **Does not exist** — manual `git pull` + `docker compose up -d --build` |
| CI/CD (`.github/workflows/`) | **Does not exist** — no `.github/` folder in this repo |
| ONLYOFFICE Document Server | **External dependency, not deployed by this repo.** `.env` has
`ONLYOFFICE_SERVER_URL`/`ONLYOFFICE_JWT_SECRET` because the app talks to one as an external
service — point those at wherever your org runs (or will run) it. |
| File storage | App currently uploads to **DigitalOcean Spaces** (`DO_SPACES_*` /
`UPLOAD_DEST_PATH` in `.env`), not the local MinIO container that ships in `docker-compose.yml` for
dev. |

For questions or issues, check `system_docs/developerguide/DEVELOPER-GUIDE.md` or contact the ITBS
engineering team.
