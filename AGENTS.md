# Package Skeleton

This repository is a Laravel package skeleton for building new packages. Preserve idiomatic Laravel package patterns, keep placeholders generic, and prefer the smallest package-specific change that keeps the skeleton useful after configuration.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep `:author_name`, `:package_name`, `:vendor_slug`, and `:package_slug` placeholders intact until the package is configured.
- Treat `configure.php` as a one-time bootstrap script: package authors run `composer install` to start configuration automatically, or `composer install --no-scripts` followed by `php configure.php` to run it manually, replacing placeholders, pruning disabled features/tools, optionally creating a GitHub repository, and deleting the script after success.
- When changing package capabilities or maintenance tooling in the skeleton, keep the configure feature/tool mappings, opt-in non-interactive feature flags, files, Composer metadata, `README_PACKAGE.md`, `AGENTS_PACKAGE.md`, AI guidance, `AGENTS.md` as the skeleton guidance source, `AGENTS_PACKAGE.md` as the package guidance source, and `.agents/skills` as the canonical local skills source in sync. `configure.php` generates package `README.md`, `AGENTS.md`, `CLAUDE.md`, and `.claude/skills` from them.
- Do not add runtime dependencies, generated files, or extra scaffold unless the package feature needs them.
- Keep temporary phase scaffold tests out of the final shipped test suite.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Workbench build: `composer build`
- Workbench server: `composer serve`

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.
- `skeleton-development`: use when changing this skeleton repository itself, including placeholders, configure flow, temporary phase tests, or scaffold-wide conventions.
