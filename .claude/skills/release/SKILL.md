---
name: release
description: Release a new backend version: tag `vX.Y.Z`, push, create the GitHub release, then start the next patch version. Use when the user asks to release, cut a release, or publish a backend version.
---

# Release

Production installs check out the newest `v*` tag of the backend, so a release is what users get. Run this only when the user asks for a release: that request is the go-ahead to tag, commit and push what's below. Nothing else gets committed along the way.

## Check

1. On `main`, `git status --porcelain` is empty, and after `git fetch origin --tags` `main` equals `origin/main`. Otherwise stop and say what's in the way.
2. `VERSION` is the version to release (`X.Y.Z`) and `git tag --list vX.Y.Z` is empty.
3. Re-read `git log --format=%s <last tag>..HEAD` (all history before the first release): the step must fit it (AGENTS.md, Releases). A wrong step is fixed with a `chore(release): set X.Y.Z` commit before tagging.
4. `composer lint:check` and `php artisan test --compact` pass.

## Release

1. `git tag -a vX.Y.Z -m "vX.Y.Z"` on the checked commit.
2. Set `VERSION` to `X.Y.(Z+1)`, run `php artisan test --compact tests/Unit/VersionTest.php`, commit `chore(release): start X.Y.(Z+1)`.
3. `git push --atomic origin main vX.Y.Z`.
4. `gh release create vX.Y.Z --verify-tag --title "vX.Y.Z" --generate-notes`.

## Report

The release URL, the version, and that `VERSION` holds the next patch. Installs pick it up on their next `moviebrowser3 update`. Backend only; a frontend release that needs this API goes after it.
