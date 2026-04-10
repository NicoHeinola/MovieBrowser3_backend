# Commit Message Guidelines

This repository uses Conventional Commits for commit messages.

## Format

```text
type(scope): short summary
```

- Use lowercase for `type` and `scope`.
- Keep the summary short, imperative, and specific.
- Do not end the summary with a period.
- Use `scope` when it helps identify the affected area.

## Common Types

- `feat`: a new user-facing feature or capability
- `fix`: a bug fix or regression fix
- `refactor`: code changes that do not change behavior
- `test`: test-only changes
- `docs`: documentation-only changes
- `chore`: tooling, maintenance, or non-product changes
- `style`: formatting or non-behavioral styling changes
- `perf`: performance improvements
- `build`: build pipeline or dependency packaging changes
- `ci`: CI workflow changes

## Scope Guidance

Use a scope when the change clearly belongs to one area, for example:

- `auth`
- `home`
- `top-navigation`
- `shows`
- `router`
- `vuetify`
- `api`

## Examples

```text
feat(auth): add register form validation
fix(home): prevent banner overflow on mobile
refactor(shows): move carousel fetch logic into store
test(utils): add coverage for getYouTubeEmbedUrl
docs(readme): document local development commands
chore(deps): update vuetify and vite
```

## Breaking Changes

If a change introduces a breaking change, use `!` after the type or scope and describe the break in the commit body.

```text
feat(api)!: rename show status filter values
```

## Recommended Body

Add a body when the reason for the change is not obvious from the summary alone.

```text
fix(auth): handle expired refresh token

Avoids leaving the UI in a logged-in state after the backend rejects
the refresh request during app startup.
```
