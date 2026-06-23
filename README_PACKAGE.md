<div align="center">
    <h1>:package_name</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/:vendor_slug/:package_slug"><img src="https://img.shields.io/packagist/v/:vendor_slug/:package_slug.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/:vendor_slug/:package_slug"><img src="https://img.shields.io/packagist/php-v/:vendor_slug/:package_slug.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/:vendor_slug/:package_slug"><img src="https://badge.laravel.cloud/badge/:vendor_slug/:package_slug?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/:vendor_slug/:package_slug/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/:vendor_slug/:package_slug/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/:vendor_slug/:package_slug"><img src="https://img.shields.io/packagist/dt/:vendor_slug/:package_slug.svg?style=flat-square" alt="Total Downloads"></a>
</p>

:package_description

## Installation

You can install the package via Composer:

```bash
composer require :vendor_slug/:package_slug
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag=":package_slug"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag=":package_slug-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag=":package_slug-migrations"
php artisan migrate
```

### Publishing the Views

```bash
php artisan vendor:publish --tag=":package_slug-views"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag=":package_slug-lang"
```

### Publishing the Public Assets

```bash
php artisan vendor:publish --tag=":package_slug-assets"
```

## Usage

<!-- Add a basic usage example here. -->

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to :package_name! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [:author_name](https://github.com/:author_username)
- [All Contributors](../../contributors)

## License

:package_name is open-sourced software licensed under the [MIT license](LICENSE.md).
