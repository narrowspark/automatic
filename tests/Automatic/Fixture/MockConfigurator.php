<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018-2020 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/automatic
 */

namespace Narrowspark\Automatic\Tests\Fixture;

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
