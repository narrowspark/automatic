<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Fixture;

use Narrowspark\Automatic\Common\Configurator\AbstractConfigurator;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;

class MockConfigurator extends AbstractConfigurator
{
    public static function getName(): string
    {
        return 'mock';
    }

    public function configure(PackageContract $package): void
    {
        foreach ($package->getConfig('mock') as $message) {
            $this->write($message);
        }
    }

    public function unconfigure(PackageContract $package): void
    {
        foreach ($package->getConfig('mock') as $message) {
            $this->write($message);
        }
    }
}
