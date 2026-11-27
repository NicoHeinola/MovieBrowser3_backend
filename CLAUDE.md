# MovieBrowser3 Backend

Guidance for Claude Code. File-specific rules live in `.claude/rules/` and load automatically for matching files.

## Workspace Defaults

- Laravel 13 JSON API (Sanctum bearer auth) for a movie/show library. Postgres in dev (`docker-compose.yml`), in-memory SQLite in tests.
- Use Composer, Artisan and Pest for project commands and validation.
- Prefer extending existing namespaces and patterns before creating new top-level structure.
- Preserve existing API response fields and status codes unless the task explicitly changes the contract.
- The API sends message codes plus params, never user-facing sentences: the frontend owns all translations. If you find the backend repeating English messages a lot, file a ticket instead of fixing it in passing.
- Every new `env()` key gets a commented line in `.env.example` (`tests/Unit/ArchitectureTest.php` checks it), and in `deploy/.env.production` when production should set it.

## Words

One word per thing, in code, API, UI texts, docs and commits:

- **Video base folder**: the folder all videos are in (setting `video_base_folder`). **Download folder**: where qBittorrent puts its files.
- **Move**: the video base folder to another place (`VideoBaseFolderMove`). **Rename**: a video within the video base folder, when its episode, entry or show changes.
- **Check**: look which video files are there and record it (`VideoFileCheck`).
- **Pick**: a torrent file chosen for an episode (`DownloadPick`). **Import**: put a finished pick's file in place as its episode's video.
- **Host bridge**: the program outside the containers that starts VLC and copies files.

## Commands

- `composer dev`: server (port 8765, `composer dev -- --port=9000` for another) plus queue worker, import worker (downloaded files, queue `imports`) and scheduler (`schedule:work`, the jobs in `routes/console.php`, such as the download sync). Runs with `--no-reload` so `PHP_CLI_SERVER_WORKERS` (4) applies: restart after `.env` changes.
- `php artisan test --compact`, or `--filter=CategoryApiTest`, or a file path.
- `composer lint:check` / `composer lint:fix`: Pint, `laravel` preset (`!$x` without a space after `!`).
- Run `composer lint:fix` and the relevant tests before calling work done.

## Run locally

- Dev database: Postgres from `docker-compose.yml` (container `moviebrowser3_backend-db-1`, port 7563, database `moviebrowser3`). Start it from the main checkout only; a worktree's folder name would make a second Compose project with clashing ports.
- A separate copy of the app (a ticket or a test) gets its own database in that same container, cloned from the dev one, and its own port:

  ```bash
  docker exec moviebrowser3_backend-db-1 createdb -U moviebrowser <db>
  docker exec moviebrowser3_backend-db-1 sh -c 'pg_dump -U moviebrowser moviebrowser3 | psql -q -U moviebrowser <db>'
  ```

  Then set `DB_DATABASE=<db>`, `APP_URL=http://localhost:<port>` and add the frontend's `localhost:<port>` to `SANCTUM_STATEFUL_DOMAINS` in its `.env`, run `php artisan migrate`, and start `composer dev -- --port=<port>`. Drop it with `docker exec moviebrowser3_backend-db-1 dropdb -U moviebrowser --force <db>`.
- Queue, cache and sessions use the `database` driver, so a separate database keeps them apart too.
- Never run `migrate:fresh`, `db:wipe` or other resetting commands in a checkout: there is no `.env.testing`, so even `--env=testing` hits that checkout's own database. Tests need none of it; `phpunit.xml` gives them in-memory SQLite.
- Video files are not copied: `video_base_folder` is a database setting, so a clone still points at the shared dev folder (`/mnt/g/New_Vid_Dev3`). Before work that moves or deletes video files, point the clone's setting at a scratch folder with a few test files.

## Architecture

Request → controller → action → model, with resources shaping the output. Requests validate, controllers orchestrate, actions hold application logic, models represent the database only, resources shape payloads. Outside systems (torrent sites, other websites and APIs, qBittorrent, the host bridge) are reached through a service in `app/Services/<System>/`, built on a Saloon connector, which actions call.

Layout per domain `X`:

```text
app/Actions/X/{Create,Update,Delete}XAction.php
app/Dtos/X/{Create,Update}XData.php
app/Http/Requests/X/{Store,Update}XRequest.php
app/Http/Resources/X/XResource.php
app/Models/X/{X.php,Query/HasXQuery.php,Relations/HasXRelations.php}
app/Http/Controllers/XController.php
database/factories/XFactory.php
tests/Feature/XApiTest.php
```

- Routes live in `routes/api.php` under `v1`: reads in the `auth:sanctum` group, writes in the `['auth:sanctum', 'admin']` group.
- An action that writes more than once does it in a `DB::transaction` (`tests/Unit/ActionTransactionTest.php`); a single write needs none.
- Enums are string-backed and live in `app/Enums`.
- `tests/Unit/ArchitectureTest.php` checks the layer rules that code can show (models don't use actions, actions get no requests, resources don't touch the disk, ...). A new checkable rule goes there, not only into prose.

## Releases

- Releases are annotated `vX.Y.Z` tags on `main`, each with a GitHub release. Installs check out the newest tag, never `main`.
- The version lives in `VERSION`.
- Size the version from the last release, and never step twice: a `fix` keeps the patch step, a `feat` raises it to the next minor, a breaking change (`!`) to the next major. The commit that needs the bigger step bumps `VERSION`; `tests/Unit/VersionTest.php` enforces it.
- No backend changelog: user-visible backend changes get a line in the frontend changelog.
- When a frontend release needs new API, release the backend first.
- Releasing follows the global `release` skill, and only on the user's explicit request. Backend specifics:
  - Version to release: `VERSION`. Before the first tag, "since the last release" means all history.
  - Checks: `composer lint:check` and `php artisan test --compact`.
  - No release commit: tag the checked commit. A wrong step is fixed first with a `chore(release): set X.Y.Z` commit.
  - Next version: set `VERSION` to the next patch and run `php artisan test --compact tests/Unit/VersionTest.php`.
  - Release notes: `--generate-notes`.
  - Report: installs pick it up on their next `~/prod_codes/MovieBrowser3/moviebrowser3 update`. A frontend release that needs this API goes after it.

## Commit Messages

Conventional Commits: `type(scope): short summary`.

- Lowercase `type` and `scope`. Keep the summary short, imperative and specific, with no trailing period.
- Types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `style`, `perf`, `build`, `ci`.
- Work for a GitHub issue uses the issue number as its scope: `feat(#20): filter shows by watch status`.
- Other changes use a domain-plural scope when they clearly belong to one area: `shows`, `categories`, `episodes`, `show-links`, `websites`, `settings`, `auth`, `api`.
- Mark a breaking change with `!` after the type or scope (`feat(api)!: rename show status filter values`) and describe the break in the body.
- Add a body when the reason for the change is not obvious from the summary.

## Customization

- **Rules** (`.claude/rules/*.md`): what files matching their `paths` look like. They load when Claude reads a matching file.
- **Skills** (`.claude/skills/<name>/SKILL.md`): this repo's moments, such as building a CRUD resource or changing the schema. Generic moments (tickets, reviews, tests, push, releases) are global skills; their repo specifics live in this file and the rules.
- A change to a convention, ownership boundary, endpoint pattern or workflow updates the affected rule or skill in the same change. A rule a test can check goes in `tests/Unit/ArchitectureTest.php` instead of prose.
