<div align="center">
    <h1>:package_name</h1>
    :package_description
</div>

<p align="center">
    <a href="https://packagist.org/packages/:vendor_slug/:package_slug"><img src="https://img.shields.io/packagist/v/:vendor_slug/:package_slug.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/:vendor_slug/:package_slug"><img src="https://img.shields.io/packagist/php-v/:vendor_slug/:package_slug.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/:vendor_slug/:package_slug"><img src="https://badge.laravel.cloud/badge/:vendor_slug/:package_slug?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/:vendor_slug/:package_slug/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/:vendor_slug/:package_slug/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/:vendor_slug/:package_slug"><img src="https://img.shields.io/packagist/dt/:vendor_slug/:package_slug.svg?style=flat-square" alt="Total Downloads"></a>
</p>

<!--delete-->
---
This repo can be used to scaffold a Laravel package. Follow these steps to get started:

1. Press the "Use this template" button at the top of this repo to create a new repo with the contents of this skeleton.
2. Run `php ./configure.php` to replace every `:token` placeholder across the package.
3. Run `composer test` to confirm the toolchain (PHPStan, Pint, Pest type coverage, Pest) is green.
4. Have fun creating your package.
---
<!--/delete-->

:package_description

## Installation

You can install the package via composer:

```bash
composer require :vendor_slug/:package_slug
```

You can publish all of the package's resources at once using the umbrella tag:

```bash
php artisan vendor:publish --tag=":package_slug"
```

Alternatively, you can publish each resource individually using the tags below.

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

Document how to use :package_name here.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to :package_name! You can read the contribution guide [here](.github/CONTRIBUTING.md).

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [:author_name](https://github.com/:author_username)
- [All Contributors](../../contributors)

## License

:package_name is open-sourced software licensed under the [MIT license](LICENSE.md).
