# This is a custom shopping cart package for Laravel projects

[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mobilenowgroup/laravel-shopping-cart/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mobilenowgroup/laravel-shopping-cart/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mobilenowgroup/laravel-shopping-cart/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mobilenowgroup/laravel-shopping-cart/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)

## Installation

You can install the package via composer:

```bash
composer require mobilenowgroup/laravel-shopping-cart
php artisan vendor:publish --provider="MobileNowGroup\ShoppingCart\ShoppingCartServiceProvider"
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="shopping-cart-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="shopping-cart-config"
```

## Usage

```php
// @todo
demo
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## License

The Proprietary License. Please see [License File](LICENSE.md) for more information.
