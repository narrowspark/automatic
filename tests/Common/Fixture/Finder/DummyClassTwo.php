<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Test\Fixture\Finder;

use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;

class DummyClassTwo implements ConfiguratorContract
{
    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'two';
    }

    /**
     * {@inheritDoc}
     */
    public function configure(PackageContract $package): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function unconfigure(PackageContract $package): void
    {
    }
}
