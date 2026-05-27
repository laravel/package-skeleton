# Installation

You can install :package_name using Composer:

```bash
composer require :vendor_slug/:package_slug
```

Laravel discovers the package service provider automatically. The service provider registers the package resources and any Artisan commands that :package_name ships.

## Publishing Resources

You can publish all package resources with the umbrella tag:

```bash
php artisan vendor:publish --tag=":package_slug"
```

If you only need one resource, publish the matching tag:

```bash
php artisan vendor:publish --tag=":package_slug-config"
php artisan vendor:publish --tag=":package_slug-migrations"
php artisan vendor:publish --tag=":package_slug-views"
php artisan vendor:publish --tag=":package_slug-lang"
php artisan vendor:publish --tag=":package_slug-assets"
```

## Running Migrations

If the package ships migrations, run them after publishing:

```bash
php artisan migrate
```

Remove publish commands for resources that :package_name does not provide.
