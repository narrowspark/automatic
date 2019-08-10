<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Fixture;

use Narrowspark\Automatic\Common\Configurator\AbstractConfigurator;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;

final class MockConfigurator extends AbstractConfigurator
{
    public static function getName(): string
    {
        return 'mock';
    }

    public function configure(PackageContract $package): void
    {
        foreach ($package->getConfig(ConfiguratorContract::TYPE, 'mock') as $message) {
            $this->write($message);
        }
    }

    public function unconfigure(PackageContract $package): void
    {
        foreach ($package->getConfig(ConfiguratorContract::TYPE, 'mock') as $message) {
            $this->write($message);
        }
    }
}
