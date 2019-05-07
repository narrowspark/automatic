<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Test\Fixture\Finder\Nested;

use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;

class DummyClassNested implements ConfiguratorContract
{
    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'nested';
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
