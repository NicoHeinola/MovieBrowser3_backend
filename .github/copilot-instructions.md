# MovieBrowser3 Backend Copilot Instructions

## Repository Defaults

- This repository is a Laravel 13 JSON API backend for movie-library data.
- Prefer extending existing backend namespaces and patterns before creating new top-level structure.
- Preserve existing API response fields and status codes unless the task explicitly changes the contract.
- Keep auth and token behavior aligned with Sanctum.

## Architecture Defaults

- Follow the request -> controller -> action split for HTTP work.
- Requests own endpoint validation and authorization.
- Controllers orchestrate HTTP flow.
- Actions hold reusable application logic.
- Models own persistence concerns such as relationships, casts, and reusable query configuration.
- Resources shape outward API payloads.

## Validation Defaults

- Use Composer, Artisan, and Pest for project commands and validation.
- Add or update the narrowest relevant tests when behavior changes.

## Instruction Maintenance

- When a change introduces or removes a durable repository convention, ownership boundary, endpoint pattern, file placement rule, or cross-file workflow, update the relevant `.github/instructions/*.instructions.md` and `SKILL.md` files in the same change set.
- Treat large refactors that standardize one pattern over another as instruction-impacting by default, even when the runtime API contract stays mostly the same.
- If no existing instruction file cleanly owns the new convention, extend the closest existing instruction or skill file before creating a new one.
- Review instruction and skill updates as part of completion for architecture-level or pattern-level changes; do not leave the repository guidance stale after the code has been standardized.

## Customization Boundaries

- Keep `.instructions.md` files focused on how a matched file family should be structured, with concrete examples when the rule could be interpreted multiple ways.
- Keep `SKILL.md` files focused on reusable task workflows, cross-file coordination, and completion checks.
- Keep `.agent.md` files focused on context delegation for larger planning, research, and review tasks rather than embedding instruction or skill content.

## Agent Usage

- Use `feature-researcher` when the main need is read-only investigation before implementation.
- Use `feature-planner` when the problem is understood enough to map the smallest coherent implementation path.
- Use `feature-reviewer` when the main job is identifying regressions, risks, or missing coverage in current changes.
