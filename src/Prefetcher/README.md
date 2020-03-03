<h1 align="center">Narrowspark Automatic Prefetcher</h1>
<p align="center">
    <a href="https://github.com/narrowspark/automatic/releases"><img src="https://img.shields.io/packagist/v/narrowspark/automatic.svg?style=flat-square"></a>
    <a href="https://php.net/"><img src="https://img.shields.io/badge/php-%5E7.3.0-8892BF.svg?style=flat-square"></a>
    <a href="https://codecov.io/gh/narrowspark/automatic"><img src="https://img.shields.io/codecov/c/github/narrowspark/automatic/master.svg?style=flat-square"></a>
    <a href="#"><img src="https://img.shields.io/badge/style-level%207-brightgreen.svg?style=flat-square&label=phpstan"></a>
    <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square"></a>
</p>

> **Note** This package is part of the [Narrowspark automatic](https://github.com/narrowspark/automatic).

## Installation

Use [Composer](https://getcomposer.org/) to install this package:

```sh
composer global require narrowspark/automatic-composer-prefetcher --dev
```

## Usage

The prefetcher will be executed when `composer require` , `composer install` or `composer update`
is used, you will experience a speed up of composer package installations.

Narrowspark Automatic Prefetcher supports on `skipping legacy package tags`.

You have two ways to skip old tags of a package.

The first one is to use the `composer.json extra` field, add `prefetcher` inside of this a `require` key,
then you packages with the version you want start skipping.

```json5
{
    "extra": {
        "prefetcher": {
            "require": {
                "symfony/symfony": "4.2.*",
                "next package": "1.*"
            }
        }
    }
}
```

And the second one is to use the global `env` variable

```bash
export AUTOMATIC_PREFETCHER_REQUIRE="symfony/symfony:4.2.*[, and you next package]"
```

## Versioning

This library follows semantic versioning, and additions to the code ruleset are performed in major releases.

## Changelog

Please have a look at [`CHANGELOG.md`](https://github.com/narrowspark/automatic/blob/master/CHANGELOG.md).

## Contributing

Please have a look at [`CONTRIBUTING.md`](https://github.com/narrowspark/automatic/blob/master/.github/CONTRIBUTING.md).

## Code of Conduct

Please have a look at [`CODE_OF_CONDUCT.md`](https://github.com/narrowspark/automatic/blob/master/.github/CODE_OF_CONDUCT.md).

## Credits

- [Daniel Bannert](https://github.com/prisis)
- [All Contributors](https://github.com/narrowspark/automatic/graphs/contributors)
- Narrowspark Automatic has been inspired by [symfony/flex](https://github.com/symfony/flex)

## License

This package is licensed using the MIT License.

Please have a look at [`LICENSE.md`](LICENSE.md).
