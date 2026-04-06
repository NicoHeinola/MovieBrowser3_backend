---
description: "Authoring rules for workspace .instructions.md files. Use when creating or editing backend instruction files under .github/instructions."
applyTo: '.github/instructions/**'
---

# Authoring Instructions

## What Instructions Are For

- Instructions describe file-specific rules that apply directly to files matched by `applyTo`.
- Keep each instruction narrow, concrete, and tied to the matched file set.
- Use `description` as the discovery surface with plain-language trigger phrases such as `controller files`, `request validation`, or `Pest tests`.
- Keep `applyTo` specific and avoid broad globs unless the rule truly belongs everywhere inside that file family.

## What Instructions Are Not For

- Instructions are not the place for multi-step workflows or repository-wide domain guidance.
- Do not turn an instruction into a general how-to guide for features that span multiple file types.