# Housepedia Data Intelligence for Laravel

[![Build Status](https://github.com/henzeb/housepedia-data-intelligence-laravel/workflows/tests/badge.svg)](https://github.com/henzeb/housepedia-data-intelligence-laravel/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/henzeb/housepedia-data-intelligence-laravel.svg?style=flat-square)](https://packagist.org/packages/henzeb/housepedia-data-intelligence-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/henzeb/housepedia-data-intelligence-laravel.svg?style=flat-square)](https://packagist.org/packages/henzeb/housepedia-data-intelligence-laravel)
[![License](https://img.shields.io/packagist/l/henzeb/housepedia-data-intelligence-laravel)](https://packagist.org/packages/henzeb/housepedia-data-intelligence-laravel)

Laravel integration for [henzeb/housepedia-data-intelligence](https://github.com/henzeb/housepedia-data-intelligence),
the PHP SDK for the Housepedia Data Intelligence API. It adds configuration, a facade, container
bindings and Laravel Collections with the metadata of the answer for every endpoint that
returns a list.

## Table of contents

- [Installation](#installation)
- [Usage](#usage)
- [Documentation](#documentation)
- [Changelog](#changelog)
- [Testing this package](#testing-this-package)
- [Security](#security)
- [Credits](#credits)
- [License](#license)

## Installation

You may install the package via Composer:

```bash
composer require henzeb/housepedia-data-intelligence-laravel
```

The core SDK is installed with it, and the service provider and the `Housepedia` facade
are registered automatically. Add your API key to `.env`:

```dotenv
HOUSEPEDIA_API_KEY=hp_...
```

## Usage

```php
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;

$address = Housepedia::addresses()
    ->withPostcode('1076LJ', 12, '2')
    ->find();

$suggestions = Housepedia::addresses()
    ->suggest('1076LJ 12-2'); // Results<AddressSuggestion>, a Laravel Collection with meta()

$streets = $suggestions->map->street()->filter()->unique();
```

## Documentation

- [Configuration](docs/configuration.md)
- [Usage](docs/usage.md)
- [Differences from the core SDK](docs/differences.md)
- [Testing your application](docs/testing.md)

Every endpoint, locator, response object and exception is documented in the
[core SDK documentation](https://github.com/henzeb/housepedia-data-intelligence#documentation).

### AI agents

The package ships [Laravel Boost](https://laravel.com/docs/boost) guidelines. Run
`php artisan boost:install` (or `boost:update`) after installing it, and your coding agent
knows how to use the Housepedia client.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Testing this package

```bash
composer test
composer analyse
composer format-check
```

## Security

See [SECURITY.md](SECURITY.md) for how to report a vulnerability.

## Credits

- [Henze Berkheij](https://github.com/henzeb)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
