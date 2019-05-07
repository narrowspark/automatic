<?php
declare(strict_types=1);
namespace Test;

use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;

class Configurator implements ConfiguratorContract
{
    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'test';
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
