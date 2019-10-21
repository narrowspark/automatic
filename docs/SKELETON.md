<h1 align="center">Narrowspark Automatic Skeleton</h1>

Narrowspark Automatic Skeleton generators allows the automation of Composer `create-project` via the
[`Narrowspark Automatic`](../README.md) Composer plugin.

Creating a Skeleton
----------------
Narrowspark Automatic Skeleton must be stored on their own repositories, outside of your Composer package repository.

Narrowspark Automatic checks all packages for the `automatic-skeleton` package type and register it to Automatic.

After the registration, it will search for all classes found in your composer.json `autoload` section. The classes are added to the `skeleton` section in your `automatic.lock` file.

The following example shows how your `composer.json` can look:

```json
{
    "name": "narrowspark/skeleton-generators",
    "type": "automatic-skeleton",
    "require": {
        "php": "^7.2",
        "ext-mbstring": "*"
    },
    "require-dev": {
        "narrowspark/automatic-common": "^0.4.0"
    },
    "autoload": {
        "psr-4": {
            "Narrowspark\\Skeleton\\Generator\\": "src/"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

`narrowspark/automatic-common` required for creating a Configurator, please add it to the `dev-require` section in your composer.json file.

To create a skeleton generator you need to extend the `Narrowspark\Automatic\Common\Generator\AbstractGenerator` class.

The example below shows you, how your generator class should look after the `Narrowspark\Automatic\Common\Generator\AbstractGenerator` was extend:

```php
<?php
declare(strict_types=1);
namespace Narrowspark\Skeleton\Generator;

use Narrowspark\Automatic\Common\Generator\AbstractGenerator;

class Generator extends AbstractGenerator
{
    /**
     * Returns the project type of the class.
     *
     * @return string
     */
    public function getSkeletonType(): string
    {
        // TODO
    }

    /**
     * Returns all requirements that should be installed.
     * 
     * @return string[]
     */
    public function getDependencies(): array
    {
        // TODO
    }

    /**
     * Returns all dev requirements that should be installed.
     * 
     * @return string[]
     */
    public function getDevDependencies(): array
    {
        // TODO
    }

    /**
     * Returns all directories that should be generated.
     *
     * @return string[]
     */
    protected function getDirectories(): array
    {
        // TODO
    }

    /**
     * Returns all files that should be generated.
     *
     * @return array
     */
    protected function getFiles(): array
    {
        // TODO
    }
}
```

> NOTE: You can always register only one skeleton type of a generator.
