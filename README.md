# Laravel Package Skeleton

A minimal starting point for building Laravel packages. Ships a hand-rolled service provider wired to Laravel's native package APIs, a full set of placeholder resources (routes, views, translations, migrations, assets, command), and a quality toolchain driven by composer scripts.

## Requirements

- PHP 8.3, 8.4, or 8.5
- Laravel 12 or 13

## Getting Started

The repository ships with placeholder identifiers (`vendor-name/skeleton`, `VendorName\Skeleton\`, config key `skeleton`). Replace them with your own package coordinates before publishing. A scripted interactive bootstrap will land in a later phase.

```bash
composer install
```

## Composer Scripts

| Command | Description |
|---------|-------------|
| `composer lint` | Format the codebase with Laravel Pint. |
| `composer lint:check` | Verify formatting without writing changes. |
| `composer analyse` | Run PHPStan via Larastan at level 6. |
| `composer test:types` | Enforce 100% Pest type coverage. |
| `composer test:unit` | Run the Pest test suite in parallel. |
| `composer test` | Run analyse, lint:check, test:types, and the Pest suite. |

## Package Structure

```
src/SkeletonServiceProvider.php           # Provider wired to native Laravel package APIs
src/Skeleton.php                          # Package root class (facade accessor target)
src/Facades/Skeleton.php                  # Skeleton facade
src/Console/Commands/SkeletonCommand.php  # Placeholder Artisan command
config/skeleton.php                       # Packaged defaults (mergeConfigFrom)
routes/skeleton.php                       # Package routes (placeholder route)
database/migrations/                      # Package migrations (publishable)
lang/en/messages.php                      # PHP translations (skeleton:: namespace)
resources/views/                          # Package views (skeleton:: namespace)
public/                                   # Publishable assets
tests/                                    # Pest test suite (Feature + Unit)
phpstan.neon.dist                         # PHPStan configuration
pint.json                                 # Pint preset
```

## Provider Hooks

The service provider wires these Laravel-native APIs with real placeholder files so each hook can be exercised and tested immediately:

- `mergeConfigFrom()` — packaged config defaults
- `loadRoutesFrom()` — package routes
- `loadViewsFrom()` — package view namespace (`skeleton::`)
- `loadTranslationsFrom()` — PHP translations (`skeleton::` namespace)
- `publishes()` — config, views, lang, public assets
- `publishesMigrations()` — migration publishing group
- `commands()` — placeholder Artisan command (guarded by `runningInConsole()`)

## Publish Tags

| Tag | Publishes |
|-----|-----------|
| `skeleton` | Everything below (umbrella tag) |
| `skeleton-config` | `config/skeleton.php` |
| `skeleton-views` | Package views |
| `skeleton-lang` | PHP translations |
| `skeleton-assets` | Public assets |
| `skeleton-migrations` | Migrations (via `publishesMigrations`) |
