---
description: Docker image and production deploy files. Use when editing the Dockerfile, docker/, deploy/ or the install and update scripts.
paths:
  - 'Dockerfile'
  - '.dockerignore'
  - 'docker/**'
  - 'deploy/**'
applyTo: 'Dockerfile,.dockerignore,docker/**,deploy/**'
---

# Deploy Rules

- Prod builds on the host from clones of GitHub `main` in `~/prod_codes/MovieBrowser3` (`MOVIEBROWSER3_DIR` overrides it). There is no registry or CI. `install.sh` is the single code path; `moviebrowser3 update` runs it from the backend clone, so it runs from `main()`, which bash parses in full before `git pull` can replace the file.
- Updates follow the saved repo URL (`remote set-url`), pull with `--ff-only` and print each repo's old and new commit. The frontend builds with `npm run build` (`node:24-alpine`, as the host user) and its `dist/` is copied to `web/next`. That's swapped in as `web/current`, which the backend serves, only after the migrations, then the backend is force-recreated. A failed pull, build or migration leaves the running app and frontend alone.
- The bridge re-registers when the checksum of its files plus port differs from `.bridge-installed`, which is written only after registering succeeds. Don't compare against the copied files: a failed run would hide the change.
- The admin prompt depends on whether the seed migration has run on this database (`migrate:status`), never on a marker file, so a recreated database can't fall back to the seed's default password. The install folder may not contain whitespace (the `.bat`, the task and the systemd unit run commands from it). Secrets reach `docker compose run` as `-e NAME` from the environment, never `-e NAME=value`, which shows in `ps`.
- Every install is the Compose project `moviebrowser3` with the local image `moviebrowser3-backend:local` (`pull_policy: never`), so `install.sh` refuses to run while that project has containers from another folder. Test stacks must set `COMPOSE_PROJECT_NAME` and other ports, or they take over the real install.
- One FrankenPHP container (classic mode) serves everything on `WEB_PORT`. `/api/*` and `/up` go to `php_server`, with no `encode` so the watch-progress stream flushes. Other paths go to `web/current` (mounted read-only at `/app/frontend`), with `try_files {path} /index.html`, `immutable` caching on `/assets/*`, `no-cache` elsewhere, and zstd/gzip.
- The site address matches any Host (`http://:{$WEB_PORT}` plus `bind {$WEB_BIND}`), so localhost, 127.0.0.1 and LAN names all work.
- `docker/php.ini` keeps `max_execution_time = 0` and `output_buffering = 0`. Each open stream holds one of `PHP_THREADS`.
- Every compose service uses `network_mode: host`: VLC's web interface (47000-47999) and the VLC bridge (8767) listen on the host's 127.0.0.1 only. Prod ports (web 8780, db 7564) must not clash with dev (3010, 8765, 7563).
- backend and queue run as the host user (`APP_UID`/`APP_GID`), so `storage`, `bootstrap/cache` and the Caddy data dirs stay world-writable in the image.
- The entrypoint caches config, routes and events only. Never `artisan optimize`: laravel-data's step writes to the database cache before the first migration. No `view:cache`: a clone has no `resources/views`. Migrations run only from `install.sh`, never on container start.
- Media folders mount at the same path as on the host (written to `compose.override.yml` by `install.sh`), so stored paths work in the container and for the bridge.
- `deploy/vlc_bridge.py` is Python 3 standard library only, binds 127.0.0.1, checks the bearer token, and only ever starts `vlc` or `vlc.exe`. Keep its HTTP contract in sync with `RequestVlcBridgeAction`.
- `install.sh` must stay safe to re-run: it keeps the secrets in `.env` and the database. It asks for every setting it owns, prefilled with the saved answer (`.env` keys, with the install folder in `~/.config/moviebrowser3/install-dir`). `--update` (what `moviebrowser3 update` runs) asks nothing. Prompts read `/dev/tty` because it runs through `curl | bash`. They read into the variable directly: `read -e` inside `$(...)` hides the typing.
- `deploy/.env.production` is the only place setting defaults live; never repeat them in `install.sh`. The installer copies it into `.env`, adds keys a newer template brings, fills empty secrets, and rewrites only the keys it asks for. A renamed, removed or forcibly changed setting also gets a new numbered line in `deploy/env-migrations` (`rename`, `remove`, `set`), applied once per install via `MOVIEBROWSER3_ENV_MIGRATION`. Never edit or renumber a line that's on main.
- When a pull brings a different `install.sh`, the running one hands over to it with `--resume` (no setting prompts, the backend's old commit carried in `MOVIEBROWSER3_BACKEND_FROM`). Keep steps before the hand-over (configure, backend pull, `write_env`) safe to run twice. Root `.env.production` stays git-ignored; `deploy/.env.production` is un-ignored and must hold no secrets.
- Every service logs through `json-file` with `max-size`/`max-file`.
