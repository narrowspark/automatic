<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Fixtures\Finder\Nested;

use Narrowspark\Discovery\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Discovery\Common\Contract\Package as PackageContract;

class DummyClassNested implements ConfiguratorContract
{
    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'nested';
    }

    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
    }
}
