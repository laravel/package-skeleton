# Installation

You can install the package via Composer:

```bash
composer require :vendor_slug/:package_slug
```

Laravel discovers the service provider automatically.

## Publishing Resources

You may publish all package resources at once:

```bash
php artisan vendor:publish --tag=":package_slug"
```

Or, publish a specific resource:

```bash
php artisan vendor:publish --tag=":package_slug-config"
php artisan vendor:publish --tag=":package_slug-migrations"
php artisan vendor:publish --tag=":package_slug-views"
php artisan vendor:publish --tag=":package_slug-lang"
php artisan vendor:publish --tag=":package_slug-assets"
```

## Running Migrations

After publishing the migrations, run them:

```bash
php artisan migrate
```
