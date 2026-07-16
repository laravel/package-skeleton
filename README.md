# Laravel Package Skeleton

A starter template for building beautiful Laravel packages.

## Introduction

This skeleton provides everything you need to start building a Laravel package. It comes pre-configured with a service provider, testing via Pest, static analysis via Larastan, code formatting via Pint, and a workbench application for end-to-end development — all wired up and ready to go.

An interactive configuration script personalizes the skeleton for your package during `composer install`, setting up your namespace, service provider, and only the features you need.

## Getting Started

Press the **Use this template** button at the top of this repository to create your package, or clone it directly:

```bash
git clone https://github.com/laravel/package-skeleton.git my-package
cd my-package
```

Then, install your dependencies. The interactive configuration script will run automatically:

```bash
composer install
```

If you prefer to configure manually, install without scripts and run the configuration separately:

```bash
composer install --no-scripts
php configure.php
```

Once configured, verify everything is working:

```bash
composer test
```

You may also boot the included workbench application to test your package end-to-end:

```bash
composer serve
```

The workbench app will be available at `http://localhost:8000`.

## Non-Interactive Configuration

The configuration script supports non-interactive mode for CI or scripted setups. Pass `--no-interaction` along with any metadata options you'd like to prefill:

```bash
php configure.php --no-interaction --config --routes
```

Non-interactive mode also activates automatically when the `COMPOSER_NO_INTERACTION=1` environment variable is set, when an AI agent is detected, or when standard input is not an interactive terminal.

Omitting feature flags includes every package feature; passing specific flags includes only those features.

Non-interactive runs print a single line of JSON describing the result, including the resolved metadata, selected features and tools, and any manual follow-up steps. Invalid metadata options fail with a JSON error before any files are changed.

During configuration, `README_PACKAGE.md` and `AGENTS_PACKAGE.md` are customized and moved to `README.md` and `AGENTS.md`, replacing the skeleton files. The script also links `CLAUDE.md` to `AGENTS.md` and `.claude` to `.agents` so both agent formats share the same guidance.

## After Setup

A few GitHub settings need your attention after creating your package repository:

- Review Dependabot pull requests before merging — this skeleton does not include an automatic merge workflow.
- Create release-note labels: `breaking`, `enhancement`, `bug`, `documentation`, `dependencies`, `maintenance`, `skip-changelog`, and `duplicate`.
- Review branch protection for `main` — changelog automation requires GitHub Actions to commit to `CHANGELOG.md` after a release.

No additional repository secrets are required; the included workflows use GitHub's built-in `GITHUB_TOKEN`.
