# MovieBrowser3 Backend

Guidance for AI coding agents (Claude Code and Copilot). File-specific rules live in `.claude/rules/` and load automatically for matching files.

## Workspace Defaults

- Laravel 13 JSON API (Sanctum bearer auth) for a movie/show library. Postgres in dev (`docker-compose.yml`), in-memory SQLite in tests.
- Use Composer, Artisan and Pest for project commands and validation.
- Prefer extending existing namespaces and patterns before creating new top-level structure.
- Preserve existing API response fields and status codes unless the task explicitly changes the contract.

## Commands

- `composer dev`: server (port 8765, `composer dev -- --port=9000` for another) plus queue worker. Runs with `--no-reload` so `PHP_CLI_SERVER_WORKERS` (4) applies: restart after `.env` changes.
- `php artisan test --compact`, or `--filter=CategoryApiTest`, or a file path.
- `composer lint:check` / `composer lint:fix`: Pint, `laravel` preset (`!$x` without a space after `!`).
- Run `composer lint:fix` and the relevant tests before calling work done.

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
- **Skills** (`.claude/skills/<name>/SKILL.md`): reusable workflows such as building a CRUD resource, changing schema or writing tests.
- When a change introduces or removes a durable convention, ownership boundary, endpoint pattern or workflow, update the affected rules or skills in the same change.
- Keep customizations concise. Record conventions, not feature behavior the code already shows. Do not add cross-file reference lists when the rules already load for matching files.

## Response Style

- Prefer terse, high-signal responses by default. Drop filler, hedging, and repeated framing.
- Lead with the technical conclusion or next action. Keep explanations compact unless the user explicitly asks for depth.
- For implementation updates, state what changed, what was validated, and any remaining blocker.
- Keep file links, validation results, assumptions, and next steps.
- If the user asks for normal or detailed mode, follow that request for the rest of the conversation or until they change it.
