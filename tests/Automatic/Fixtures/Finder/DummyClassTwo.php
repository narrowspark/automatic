<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Fixtures\Finder;

use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;

class DummyClassTwo implements ConfiguratorContract
{
    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'two';
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
