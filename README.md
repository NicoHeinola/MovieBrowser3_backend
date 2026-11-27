# MovieBrowser3 Backend API

The API of MovieBrowser3, a show and movie library that runs on your own PC. It is a Laravel 13 JSON API. It manages shows, episodes and their video files, watch status and progress, playback in VLC through a host bridge, and torrent search and downloads through qBittorrent. The web app lives in the `MovieBrowser3_frontend` repo.

## Setup

Start the dev database first, then set up the app:

```bash
docker compose up -d
composer setup
```

Database backups need `pg_dump` 17 on the host (the production image has it). On Ubuntu:

```bash
sudo apt install -y postgresql-common
sudo /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
sudo apt install -y postgresql-client-17
```

Dumps go to `storage/app/backups` unless `BACKUP_PATH` says otherwise.

## Run

```bash
composer dev
```

It starts the server on port 8765, the queue and import workers, and the scheduler.

## API

- Every route is under `/api/v1`. List them with `php artisan route:list --path=api`.
- Auth uses Sanctum bearer tokens (`Authorization: Bearer <token>`) from `POST /api/v1/auth/login`.
- Reads need a signed-in user. Most writes, and calls to outside systems such as the torrent search, need an admin.
- `GET /api/v1/watch-progress/stream` is a server-sent events stream of the user's watch progress and watch status changes.

## Test

```bash
composer test
```

## Production

A Docker deploy, built on the host from the newest release tag of each repo, with a host-side VLC bridge. [deploy/README.md](deploy/README.md) is the step-by-step install guide, and [deploy/TECHNICAL.md](deploy/TECHNICAL.md) explains how it works. To publish, release the backend and then the frontend, and run `~/prod_codes/MovieBrowser3/moviebrowser3 update`.

## AI Agent Guidance

Claude Code reads `CLAUDE.md`, the file-specific rules in `.claude/rules/` and the workflows in `.claude/skills/`.
