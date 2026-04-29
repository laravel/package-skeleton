# Package Skeleton

This repository is a Laravel package skeleton for building new packages. Preserve idiomatic Laravel package patterns, keep placeholders generic, and prefer the smallest package-specific change that keeps the skeleton useful after configuration.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep `:author_name`, `:package_name`, `:vendor_slug`, and `:package_slug` placeholders intact until the package is configured.
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

- `package-scaffold`: use when adding package capabilities, files, or package shape changes.
- `package-service-provider`: use when wiring config, routes, views, translations, migrations, assets, commands, or package metadata.
- `package-testing`: use when adding or changing package tests with Pest 4 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-docs`: use when writing README, MkDocs, contributing, upgrade, or usage documentation.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation and docs.
- `skeleton-development`: use when changing this skeleton repository itself, including placeholders, configure flow, temporary phase tests, or scaffold-wide conventions.
