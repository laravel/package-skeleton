# Laravel Package Skeleton

This repository is a starter for building Laravel packages. It includes a one-time configuration script, common package resources, development tooling, optional maintenance workflows, and AI guidance for package authors.

## What This Skeleton Provides

- Interactive one-time package configuration during `composer install`, or manually with `php configure.php`.
- Laravel-native service provider wiring for config, routes, views, translations, migrations, assets, commands, and facades.
- Pest, Larastan, Pint, Testbench, type coverage, and workbench scripts ready for package development.
- Optional maintenance tooling for Dependabot update pull requests, changelog automation, funding, security policy, and issue templates.
- AI guidance and reusable local skills for package authors.

## Getting Started

Use this repository to scaffold a Laravel package:

1. Press the "Use this template" button at the top of this repo to create a new repo with the contents of this skeleton, or clone it directly:

```bash
git clone https://github.com/laravel/package-skeleton.git my-package
cd my-package
```

2. Run `composer install` to install dependencies and start the interactive package configuration.
3. Run `composer test` to confirm the toolchain is green.
4. Run `composer build` to rebuild the bundled workbench app under `workbench/`.
5. Run `composer serve` to boot the workbench app at `http://localhost:8000` and test your package end-to-end.

If you prefer to run configuration manually, run `composer install --no-scripts` first and then `php configure.php`.

For non-interactive configuration, pass `--no-interaction` with any metadata options you want to prefill. Omitting feature flags includes every package feature; passing feature flags includes only those features, such as `php configure.php --no-interaction --config --routes`.

During configuration, `README_PACKAGE.md` and `AGENTS_PACKAGE.md` are customized and moved to `README.md` and `AGENTS.md`, replacing skeleton-facing files in the generated package. The script also links `CLAUDE.md` to `AGENTS.md` and `.claude` to `.agents` so both agent formats share the same guidance.

## Manual GitHub Follow-up

Some GitHub settings need attention after you create your package repository:

1. Review Dependabot pull requests before merging. This skeleton does not include an automatic merge workflow.
2. Create the release-note labels: `breaking`, `enhancement`, `bug`, `documentation`, `dependencies`, `maintenance`, `skip-changelog`, and `duplicate`.
3. Review branch protection for `main` — changelog automation requires GitHub Actions to commit `CHANGELOG.md` after a release.

No additional repository secrets are required; the workflows use GitHub's built-in `GITHUB_TOKEN`.
