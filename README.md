# Laravel Package Skeleton

A minimal starting point for building Laravel packages. This phase-1 scaffold ships a hand-rolled service provider, a packaged config file, and the quality toolchain wired through composer scripts.

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
src/SkeletonServiceProvider.php  # Hand-rolled provider (mergeConfigFrom, guarded publishes)
config/skeleton.php              # Packaged defaults
tests/                           # Pest test suite (Feature + Unit)
phpstan.neon.dist                # PHPStan configuration
pint.json                        # Pint preset
```
