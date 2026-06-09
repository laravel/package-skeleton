# Laravel Package Skeleton

This repository is a starter for building Laravel packages. It includes a one-time configuration script, common package resources, development tooling, optional maintenance workflows, and AI guidance for package authors.

## What This Skeleton Provides

- Interactive one-time package configuration with `php ./configure.php`.
- Laravel-native service provider wiring for config, routes, views, translations, migrations, assets, commands, and facades.
- Pest, Larastan, Pint, Testbench, type coverage, and workbench scripts ready for package development.
- Optional maintenance tooling for Dependabot update pull requests, changelog automation, funding, security policy, issue templates, and VitePress documentation.
- AI guidance and reusable local skills for package authors.

## Getting Started

Use this repository to scaffold a Laravel package:

1. Press the "Use this template" button at the top of this repo to create a new repo with the contents of this skeleton, or clone it directly:

```bash
git clone https://github.com/laravel/package-skeleton.git my-package
cd my-package
```

2. Run `composer setup` to install dependencies and start the interactive package configuration.
3. Run `composer test` to confirm the toolchain is green.
4. Run `composer build` to rebuild the bundled workbench app under `workbench/`.
5. Run `composer serve` to boot the workbench app at `http://localhost:8000` and test your package end-to-end.

If you prefer to run the steps manually, run `composer install` first and then `php ./configure.php`.

For non-interactive configuration, pass `--no-[feature]` flags to remove features you do not want, such as `php ./configure.php --no-config --no-routes`.

During configuration, `README_PACKAGE.md` is customized and moved to `README.md`, replacing this skeleton README in the generated package.

## Manual GitHub Follow-up

Some GitHub automation and maintenance choices need attention after you create your package repository:

1. Enable GitHub Pages and set the source to GitHub Actions so `.github/workflows/docs.yml` can deploy the VitePress site.
2. Review Dependabot dependency update pull requests before merging them. This skeleton intentionally does not include a Dependabot automatic merge workflow.
3. Create the labels used by generated release notes if you want clean categories: `breaking`, `enhancement`, `bug`, `documentation`, `dependencies`, `maintenance`, `skip-changelog`, and `duplicate`.
4. Review branch protection rules for `main`. The changelog workflow needs GitHub Actions to be allowed to commit `CHANGELOG.md` after a release is published.

No additional repository secrets are required; the workflows use GitHub's built-in `GITHUB_TOKEN`.

## Previewing Documentation Locally

The documentation scaffold uses VitePress. To preview it locally, install npm dependencies and run the development server:

```bash
npm install
npm run docs:dev
```

Then open the local URL shown by VitePress, usually `http://localhost:5173/`.
