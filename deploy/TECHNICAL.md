# MovieBrowser3 Deploy: Technical Details

How the production deploy works. For installing it, see [README.md](README.md).

## Architecture

MovieBrowser3 runs in Docker on WSL2 (Docker Engine inside WSL, mirrored networking) or on plain Linux. It's built on the same machine from clones of GitHub (the newest release tag of each): no registry, no CI. A small host-side VLC bridge does the one thing a container can't: start VLC.

```text
browser ─▶ backend (FrankenPHP)  127.0.0.1:8780   /api/*, /up → Laravel; everything else → the built frontend
           queue (queue:work)
           scheduler (schedule:work)
           db (Postgres 17)      127.0.0.1:7564
backend / queue ── POST /launch, /windows-paths ─▶ VLC bridge  127.0.0.1:8767 ─▶ vlc.exe
backend / queue ── status.json ─▶ VLC web interface  127.0.0.1:40000-49151
```

Every service uses the host network, because VLC's web interface and the bridge listen on the host's 127.0.0.1 only. The ports stay clear of dev (frontend 3010, backend 8765, dev db 7563). With network access on (`WEB_BIND=0.0.0.0`), only the app port opens to the LAN; the database and the bridge stay on 127.0.0.1.

## Install and Update

`install.sh` is the only code path; `moviebrowser3 update` runs it from the backend clone with `--update`. It works through `curl | bash`, so prompts read `/dev/tty`. Each run:

1. Checks for WSL2, mirrored networking (warns only), git, Docker, Docker Compose and python3.
2. Asks for the settings (below), unless `--update`, `--resume` or no terminal. It then refuses to go on while containers of the Compose project `moviebrowser3` belong to another install folder: every install shares that project, so a second one would take over the first one's containers.
3. Clones or fetches the backend into `<install folder>/MovieBrowser3_backend`, following a changed repo URL, and checks out its newest release tag (`vX.Y.Z`, detached). Commits on `main` after that tag are unreleased and never built. With no release tag yet, it checks out `main` and fast-forwards it. It prints the old and new version (the tag, or the short commit when it's between tags).
4. Writes `.env` (below).
5. If the backend update brought a different `install.sh`, it hands over to it with `--resume`: the same answers, no prompts, and the backend's old version carried in `MOVIEBROWSER3_BACKEND_FROM`. Installer changes apply on the update that brings them. The very first update after this feature exists still runs the old installer.
6. Clones or fetches the frontend into `<install folder>/MovieBrowser3_frontend` and checks out its newest release tag the same way, with the same `main` fallback. The two repos are versioned independently.
7. Copies `compose.yml`, the bridge, the `moviebrowser3` command and the task script from the backend clone, and links `moviebrowser3` into `~/.local/bin`.
8. Writes `compose.override.yml` with the media folders.
9. Builds the frontend: `npm ci && npm run build` in `node:24-alpine`, as the host user, with no Node on the host. The clone's git-ignored `dist/` is copied to `web/next`.
10. Builds the backend image.
11. Runs the migrations in a one-off container (`docker compose run --rm backend php artisan migrate --force`). Containers never migrate on start. When the admin seed migration hasn't run on this database yet (a new or recreated one), it asks for the admin username and password and passes them to that run only, through the environment (`-e NAME`), so they don't show in the process list. So the password is never stored, and the seed never falls back to its `admin` / `password` default.
12. Swaps `web/next` in as `web/current`, recreates the backend so its mount picks it up, and starts the rest.
13. Registers the VLC bridge when it differs from the last one registered (below).
14. On WSL, writes `windows/update_movie_browser_3.bat`: `wsl.exe -d <distro> -e bash -lc "<install folder>/moviebrowser3 update"`, then `pause`.

A failed fetch, checkout, build or migration stops the run before step 12, so the previous frontend and containers keep running. `install.sh` runs everything from `main()`, which bash parses in full first, so a backend checkout that replaces the file mid-run is safe.

## Settings

| Prompt | Saved as | Default |
| --- | --- | --- |
| Install folder (no spaces) | `~/.config/moviebrowser3/install-dir` | `~/prod_codes/MovieBrowser3` |
| Backend and frontend repo | `MOVIEBROWSER3_BACKEND_REPO`, `MOVIEBROWSER3_FRONTEND_REPO` | the GitHub repos |
| Media folders, one per prompt | `MOVIEBROWSER3_MEDIA_DIRS` (`:`-separated) | none |
| App port | `WEB_PORT`, `APP_URL` | 8780 |
| Reachable from the network? | `WEB_BIND` (`0.0.0.0` or `127.0.0.1`) | no |
| Database port, VLC bridge port | `DB_PORT`, `VLC_BRIDGE_PORT`, `VLC_BRIDGE_URL` | 7564, 8767 |
| Admin username and password | not saved | `admin` |

Each prompt is prefilled with the saved answer (`read -e -i`, so Tab completes paths). `MOVIEBROWSER3_DIR`, `MOVIEBROWSER3_BACKEND_REPO`, `MOVIEBROWSER3_FRONTEND_REPO`, `MOVIEBROWSER3_MEDIA_DIRS`, `MOVIEBROWSER3_ADMIN_USERNAME` and `MOVIEBROWSER3_ADMIN_PASSWORD` override the saved answers, e.g. for automation without a terminal. `moviebrowser3` finds its install folder from its own resolved path, whatever `MOVIEBROWSER3_DIR` says.

### `.env`

[`.env.production`](.env.production) is the template and the only place defaults live. The first run copies it (mode 600) and fills the secrets (`APP_KEY`, `DB_PASSWORD`, `VLC_BRIDGE_TOKEN`) and the host user (`APP_UID`, `APP_GID`). Every run then rewrites only the answered keys. Other keys, and manual edits, stay. After editing `.env`, run `moviebrowser3 start`.

When settings change between versions:

- **New setting:** add it to `.env.production`. Every run adds keys that `.env` lacks, with their defaults.
- **Renamed, removed, or a default that must change on existing installs:** add a numbered line to [`env-migrations`](env-migrations) (`rename OLD NEW`, `remove KEY`, `set KEY value`), and change `.env.production` the same way. Each line runs once per install; `.env` remembers the last one applied in `MOVIEBROWSER3_ENV_MIGRATION`. A new install starts from the template and skips them all. Never edit or renumber a line that's on `main`.
- A changed default in `.env.production` alone doesn't reach existing installs, because the installer can't tell an old default from a manual edit.

## Files in the Install Folder

| File | |
| --- | --- |
| `MovieBrowser3_backend/`, `MovieBrowser3_frontend/` | The clones, each on its newest release tag. Don't edit them: local changes block the update. To recover one, `git -C <clone> reset --hard` (plus `clean -fd` for added files) and re-run. |
| `.env` | Settings, secrets and installer answers. Compose, the containers and the bridge read it. |
| `compose.yml` | The stack, copied from the backend clone on every run. `backend`, `queue` and `scheduler` use the locally built `moviebrowser3-backend:local` with `pull_policy: never`. |
| `compose.override.yml` | Media folder mounts, rewritten on every run. |
| `web/current/` | The frontend being served. `web/next/` exists only during an update. |
| `data/postgres/` | The database. |
| `storage/app/` | Laravel's local storage, including `storage/app/videos` while `video_base_path` is empty. |
| `vlc_bridge.py`, `windows/` | The bridge, its task script and the update `.bat`. |
| `.bridge-installed` | Checksum of the bridge files and port that were last registered. |

Logs go through Docker's `json-file` driver, capped at 3 × 10 MB per service.

## Media Folders

Each media folder is mounted into `backend` and `queue` at the same path as on the host (`/mnt/g/New_Vid:/mnt/g/New_Vid`), so the full paths stored in the database work in the containers and for VLC. They're read-write, because the backend stores, moves and deletes episode files.

Windows drives appear as `/mnt/<letter>` (drvfs). WSL only mounts drives present when it starts, and the bridge task keeps WSL running, so an external disk plugged in later needs `sudo mount -t drvfs G: /mnt/g` (or an `/etc/fstab` line with `nofail`, see README step 5). Containers only see mounts that existed when they started, so run `moviebrowser3 restart` afterwards. The installer warns when a `/mnt/<letter>` folder's drive isn't mounted. A disk with a Linux filesystem mounts with `wsl --mount \\.\PHYSICALDRIVE<n> --partition <p>` from an administrator PowerShell, under `/mnt/wsl/`.

## Scheduled Jobs

`scheduler` runs `schedule:work`, which only queues what `routes/console.php` schedules; `queue` does the work, so `scheduler` needs no media folders.

- **Daily:** `CheckEpisodeVideoFilesJob` refreshes every episode's stored `has_video_file`, 200 episodes per job so VLC tracking keeps running on the shared worker. Admins can start it with `POST /api/v1/video-files/check` and follow it with `GET` on the same path. Each run is a row in `video_file_checks`. Starting one while another runs answers 422 `video_file_check_running`, a unique index backs that up for simultaneous starts, and a run that fails reports `video_file_check_failed`. If the worker is killed before it can report that, the run counts as dead after 5 minutes without progress, so it can't block the next one.
- While any episode expects a file, a video base folder that doesn't exist or can't be read stops the check: starting one answers 422 `video_base_path_unavailable`, and a run already going (or the daily one) ends with that code without changing anything further.
- An empty base folder doesn't stop it: the check flags those episodes as missing and finishes without an error, and the status's `missing_video_files_count` shows the result: episodes whose uploaded file is gone, not ones that never had a file. A drive that isn't mounted looks like an empty folder in the containers, so nearly everything showing as missing usually means that. The next check with the drive back restores the flags.

## Serving

One FrankenPHP container (classic mode) serves everything on `WEB_PORT`:

- `/api/*` and `/up` go to Laravel (`php_server`), uncompressed so the watch-progress stream isn't held back.
- Every other path serves `web/current`, mounted read-only at `/app/frontend`. Unknown paths get `index.html`, so routes like `/shows/12` load the app.
- `/assets/*` has hashed names and is cached for a year. Everything else is `no-cache`, so a new build shows on the next load. Frontend files are compressed with zstd or gzip.
- The site address matches any Host header, so localhost, 127.0.0.1 and LAN names all work.

FrankenPHP runs `PHP_THREADS` threads (default 24), and each open watch-progress stream holds one. `max_execution_time` is 0, output buffering is off, and uploads have no size cap or timeout. The entrypoint caches config, routes and events, but not views: a clone has no `resources/views`. It doesn't run `artisan optimize`, because laravel-data's step writes to the database cache before the first migration. `backend`, `queue` and `scheduler` run as the host user, so files they write on the media folders stay yours.

## VLC Bridge

`vlc_bridge.py` (Python 3 standard library) listens on `127.0.0.1:${VLC_BRIDGE_PORT}` and needs `Authorization: Bearer ${VLC_BRIDGE_TOKEN}`, both read from the `.env` next to it:

- `POST /launch {"binary", "port", "password"}` starts VLC detached, with its web interface on that port. It returns 204, or 422 when the binary is missing or isn't named `vlc` / `vlc.exe`. The bridge never starts anything else.
- `POST /windows-paths {"paths": [...]}` returns `{"paths": [...]}` converted with `wslpath -w`, or 422.
- `POST /check-binary {"binary"}` returns 204 when that binary passes the same check as `/launch`, or 422 with the reason as `message` and `code` (`vlc_not_found`, `vlc_wrong_binary`). The first-login setup wizard uses it to find and test VLC.
- A bad token gets 403, anything else 404.

The backend uses it when `VLC_LAUNCHER=bridge`, which `compose.yml` sets. The setup wizard's media-folder rule comes from `MOVIEBROWSER3_MEDIA_DIRS` too (`config('deploy.media_folders')`): in prod, a video folder outside them is invisible to the containers and VLC. In dev it stays `local` and starts VLC itself.

**WSL.** `windows/register-vlc-bridge.ps1` registers the "MovieBrowser3 VLC bridge" task. At logon it runs `conhost.exe --headless wsl.exe -d <distro> -e python3 <install folder>/vlc_bridge.py`, with no window. Started through `wsl.exe`, the bridge has Windows interop, so VLC appears on the desktop. It also keeps WSL, and with it Docker, running. Remove the task with `powershell.exe -File register-vlc-bridge.ps1 -Unregister`.

**Linux.** The bridge runs as the `moviebrowser3-vlc-bridge` user service. VLC needs the graphical session, so if it doesn't appear, run `systemctl --user import-environment DISPLAY WAYLAND_DISPLAY` from the desktop session and restart the service.

The installer registers and restarts the bridge only when the checksum of `vlc_bridge.py`, the task script and the port differs from `.bridge-installed`, which it writes after registering succeeds. A failed update therefore retries the registration next time.

## Troubleshooting

- **"can't check out release":** the clone has local changes. Check `git -C <clone> status`, then `git -C <clone> reset --hard`, plus `git -C <clone> clean -fd` if it lists added files, and re-run.
- **"can't be fast-forwarded":** only while a repo has no release tag yet, when it follows `main`. The clone has local commits or changes: check `git -C <clone> status`, then `git -C <clone> reset --hard origin/main`.
- **Playback answers 503 "The VLC bridge … did not answer":** the bridge isn't running. On Windows, run the task from Task Scheduler. On Linux, check `systemctl --user status moviebrowser3-vlc-bridge`.
- **VLC opens, but the playing episode is never tracked:** mirrored networking is off. Set `networkingMode=mirrored` under `[wsl2]` in `%UserProfile%\.wslconfig`, then run `wsl --shutdown`.
- **Videos are missing:** the disk isn't mounted in WSL, or it was mounted after the containers started. Mount it, then `moviebrowser3 restart`.
- **One-off commands:** `moviebrowser3 artisan <command>`, e.g. `moviebrowser3 artisan migrate:status`.
- **MovieBrowser2 import:** `moviebrowser3 import-moviebrowser2 <export .json> <folder>` runs `moviebrowser2:import-shows` in a one-off container with the export and the MovieBrowser2 folder mounted read-only (the folder at its host path, passed as `--source-root`), so it needn't be a media folder.
