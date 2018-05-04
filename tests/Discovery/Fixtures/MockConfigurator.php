<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Fixtures;

use Narrowspark\Discovery\Common\Configurator\AbstractConfigurator;
use Narrowspark\Discovery\Common\Contract\Package as PackageContract;

class MockConfigurator extends AbstractConfigurator
{
    public static function getName(): string
    {
        return 'mock';
    }

    public function configure(PackageContract $package): void
    {
        foreach ($package->getConfiguratorOptions('mock') as $message) {
            $this->write($message);
        }
    }

    public function unconfigure(PackageContract $package): void
    {
        foreach ($package->getConfiguratorOptions('mock') as $message) {
            $this->write($message);
        }
    }
}
