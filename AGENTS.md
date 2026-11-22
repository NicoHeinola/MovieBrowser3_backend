# MovieBrowser3 Backend

Guidance for AI coding agents (Claude Code and Copilot). File-specific rules live in `.claude/rules/` and load automatically for matching files.

## Workspace Defaults

- Laravel 13 JSON API (Sanctum bearer auth) for a movie/show library. Postgres in dev (`docker-compose.yml`), in-memory SQLite in tests.
- Use Composer, Artisan and Pest for project commands and validation.
- Prefer extending existing namespaces and patterns before creating new top-level structure.
- Preserve existing API response fields and status codes unless the task explicitly changes the contract.
- Every new `env()` key gets a commented line in `.env.example` (`tests/Unit/ArchitectureTest.php` checks it), and in `deploy/.env.production` when production should set it.

## Commands

- `composer dev`: server (port 8765, `composer dev -- --port=9000` for another) plus queue worker. Runs with `--no-reload` so `PHP_CLI_SERVER_WORKERS` (4) applies: restart after `.env` changes.
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
- Video files are not copied: `video_base_path` is a database setting, so a clone still points at the shared dev folder (`/mnt/g/New_Vid_Dev3`). Before work that moves or deletes video files, point the clone's setting at a scratch folder with a few test files.

## Architecture

Request → controller → action → model, with resources shaping the output. Requests validate, controllers orchestrate, actions hold application logic, models represent the database only, resources shape payloads.

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
- Every action that writes to the database wraps `handle()` in `DB::transaction`; `tests/Unit/ActionTransactionTest.php` enforces it.
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
  - Report: installs pick it up on their next `moviebrowser3 update`. A frontend release that needs this API goes after it.

## Commit Messages

Conventional Commits: `type(scope): short summary`.

- Lowercase `type` and `scope`. Keep the summary short, imperative and specific, with no trailing period.
- Types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `style`, `perf`, `build`, `ci`.
- Work for a GitHub issue uses the issue number as its scope: `feat(#20): filter shows by watch status`.
- Other changes use a domain-plural scope when they clearly belong to one area: `shows`, `categories`, `episodes`, `show-links`, `websites`, `settings`, `auth`, `api`.
- Mark a breaking change with `!` after the type or scope (`feat(api)!: rename show status filter values`) and describe the break in the body.
- Add a body when the reason for the change is not obvious from the summary.

## Customization

`.claude/` is the single source of truth. Copilot reads it too; `.github/copilot-instructions.md` and `.github/skills` are symlinks into it.

- **Rules** (`.claude/rules/*.md`): "files matching X should look like Y". Each rule has a `paths` list and the same globs in `applyTo` for Copilot.
- **Skills** (`.claude/skills/<name>/SKILL.md`): project-specific workflows such as building a CRUD resource or changing schema. Generic workflows (tests, releases, reviews, tickets) are global Claude skills; their project specifics live in this file and the rules.
- When a change introduces or removes a durable convention, ownership boundary, endpoint pattern or workflow, update the affected rules or skills in the same change.
- Keep customizations concise. Record conventions, not feature behavior the code already shows. Do not add cross-file reference lists when the rules already load for matching files.

## Response Style

- Prefer terse, high-signal responses by default. Drop filler, hedging, and repeated framing.
- Lead with the technical conclusion or next action. Keep explanations compact unless the user explicitly asks for depth.
- For implementation updates, state what changed, what was validated, and any remaining blocker.
- Keep file links, validation results, assumptions, and next steps.
- If the user asks for normal or detailed mode, follow that request for the rest of the conversation or until they change it.
