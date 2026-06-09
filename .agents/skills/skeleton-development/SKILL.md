---
name: skeleton-development
description: "Use this skill when changing this Laravel package skeleton repository itself: placeholders, configure flow, starter defaults, root guidance, local skills, temporary phase tests, dist hygiene, or scaffold-wide conventions. Do not use for ordinary package feature work after a package has been configured."
license: MIT
metadata:
  author: laravel
---

# Skeleton Development

## Primary Goal

Evolve the starter kit without making it less useful for future package authors.

## Workflow

1. Preserve generic placeholders such as `:author_name`, `:package_name`, `:vendor_slug`, and `:package_slug` until the configure flow replaces them.
2. Keep starter guidance minimal and split skeleton-maintenance rules from package-author rules.
3. Use temporary phase scaffold tests when proving repository shape, file parity, or generated scaffolding; delete those tests before final validation.
4. Maintain package guidance only in `AGENTS.md` and local skills only in `.agents/skills`; `configure.php` copies them to `CLAUDE.md` and `.claude/skills` during package configuration.
5. Keep `configure.php` feature and tool pruning maps, non-interactive `--no-[feature]` flags, service provider wiring, Composer metadata, `README_PACKAGE.md`, docs, AI guidance, skills, and publishable files aligned.
6. Keep configure-only dependencies in `require-dev`, include them in configure dependency checks, and prune them from configured package Composer metadata.
7. Keep development-time authoring files out of Composer dist archives with `.gitattributes` when they are not runtime package files.

## References

- `AGENTS.md`
- Generated `CLAUDE.md`
- `README.md`
- `README_PACKAGE.md`
- `.agents/skills/`
- Generated `.claude/skills/`
- `configure.php`
- `.gitattributes`
- `PLAN.md`, `REQS.md`, and `phases/` when present

## Examples

- Add or rename local guidance under `AGENTS.md` or `.agents/skills`, then update front matter names, root guidance, and any scaffold consistency checks.
- Add temporary Phase tests to lock down generated file shape, run them to prove the change, then delete them before the final `composer test`.
- Update package-facing README content in `README_PACKAGE.md`; `README.md` should stay focused on using this skeleton.
- When adding a selectable package feature, add both the starter files and the matching configure pruning behavior so package authors can opt out cleanly.
- When adding or renaming a selectable feature key, verify the derived non-interactive `--no-[feature]` flag still disables the expected generated files.
- When adding a configure-only helper package, update `dependenciesAreInstalled()`, `updateComposerJson()` pruning, and temporary copied-skeleton configure checks together.

## Anti-Patterns

- Leaving temporary phase tests in the shipped starter suite.
- Putting skeleton-maintenance rules into package-facing skills.
- Replacing placeholders with one real package name in starter files.
- Adding runtime dependencies for repository-maintenance convenience.
