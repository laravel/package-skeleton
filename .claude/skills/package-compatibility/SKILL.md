---
name: package-compatibility
description: "Use this skill when reviewing Laravel package compatibility across composer constraints, PHP versions, Laravel versions, Testbench versions, dependency stability lanes, Windows CI, or matrix-sensitive code and workflow changes."
license: MIT
metadata:
  author: laravel
---

# Package Compatibility

## Primary Goal

Keep package code, dependencies, and workflows compatible with the supported Laravel 12/13 and PHP 8.3+ matrix.

## Workflow

1. Read `composer.json` first to determine PHP, Laravel, and Testbench constraints.
2. Check changed code against Laravel 12/13 APIs and PHP 8.3+ syntax before adopting newer framework or language features.
3. Review `.github/workflows/tests.yml` for dependency stability lanes, prefer-lowest coverage, prefer-stable coverage, and Windows concerns.
4. When changing dependencies, confirm constraints still allow the intended Laravel and Testbench versions.
5. Validate with the smallest local command available, then rely on CI for full OS and dependency matrix coverage.

## References

- `composer.json`
- `.github/workflows/tests.yml`
- `phpstan.neon.dist`
- `tests/`
- `workbench/`

## Examples

- Review a new Laravel API call by checking whether it exists in Laravel 12 and Laravel 13 before merging it into shared package code.
- Review a dependency bump by checking Composer constraints, Testbench constraints, prefer-lowest behavior, and Windows path assumptions.

## Anti-Patterns

- Assuming the latest local dependency version represents the whole support matrix.
- Adding PHP syntax or Laravel APIs that exceed `composer.json` constraints.
- Ignoring Windows path separators, executable assumptions, or shell-only syntax in tests and workflows.
- Removing dependency stability lanes because they are slower than a single happy path.
