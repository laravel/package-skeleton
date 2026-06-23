---
name: package-scaffold
description: "Use this skill when adding Laravel package capabilities or wiring them through the service provider: commands, migrations, routes, config merges, views, translations, assets, middleware, publish tags, workbench files, or console-only behavior."
license: MIT
metadata:
  author: laravel
---

# Package Scaffold

## Primary Goal

Add package features in the right place and wire them through the service provider using explicit Laravel APIs, keeping package names, namespaces, config keys, and publish tags consistent.

## Workflow

1. Inspect the existing package structure, sibling examples, README setup notes, and the current service provider before creating files.
2. Identify whether the request touches commands, migrations, routes, config, views, translations, assets, middleware, tests, README/contributing docs, compatibility, or release flow.
3. Create the capability files under Laravel-native package paths and use the configured package names, namespaces, publish tags, URLs, and badges consistently.
4. Wire the capability through the service provider using the patterns in *Provider wiring* below.
5. Use `package-testing` for coverage, update README or contributing documentation when user-facing behavior changes, use `package-compatibility` for matrix-sensitive changes, and use `package-release` for release tasks.
6. Add only the files needed for the requested capability and validate with the narrowest relevant command before broader checks.

## Provider Wiring

1. Keep the existing service provider method split. If the package uses names like `bootConfig`, `bootRoutes`, `bootViews`, `bootTranslations`, `bootMigrations`, `bootAssets`, or `bootCommands`, add new behavior to the matching concern.
2. Put container bindings and `mergeConfigFrom` calls in `register()` when the host app must be able to override configuration.
3. Put resource loading in boot-time methods with Laravel-native APIs such as `loadRoutesFrom`, `loadViewsFrom`, and `loadTranslationsFrom`.
4. Guard console-only publishing and command registration with `runningInConsole()` before calling `publishes`, `publishesMigrations`, or `commands`.
5. Name publish tags with the `:package_slug-*` convention so consumers can target individual resource groups.
6. Add tests for the observable provider behavior: merged config, loaded routes, publish tags, or command registration.

Provider wiring anti-patterns:

- Loading host app state too early during provider registration.
- Calling `env()` outside config files; use config values after `mergeConfigFrom` instead.
- Registering web-only concerns unconditionally when the package can run in console contexts.
- Replacing explicit provider methods with `spatie/laravel-package-tools` by default.

## References

- `src/*ServiceProvider.php`
- `config/*.php`
- `routes/*.php`
- `resources/views/`
- `lang/`
- `database/migrations/`
- `public/`
- `src/Console/Commands/`
- `tests/Feature/` and `tests/Unit/`

## Examples

- Add an Artisan command: create the command class under `src/Console/Commands`, register it in the `commands` array inside the `runningInConsole()` guard, add a feature test for observable console output, and document the command if it is user-facing.
- Add a publishable migration: place the migration in `database/migrations`, wire it through a console-guarded `publishesMigrations` call with a `:package_slug-migrations` tag, and test publish behavior with Testbench.
- Wire a new publish tag by adding a `publishes` map inside the existing console-guarded publishing method and naming the tag with `:package_slug-*`.

## Anti-Patterns

- Adding unused files because a package might need them later.
- Mixing package names, namespaces, config keys, or publish tags.
- Changing dependencies without approval.
- Replacing explicit Laravel package code with a helper abstraction when one feature-specific change would do.
