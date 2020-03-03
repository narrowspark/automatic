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

namespace Narrowspark\Automatic;

use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Contract\PackageConfigurator as PackageConfiguratorContract;

final class PackageConfigurator extends AbstractConfigurator implements PackageConfiguratorContract
{
    /**
     * Get a package configurator.
     */
    protected function get(string $key): ConfiguratorContract
    {
        $class = $this->configurators[$key];

        return new $class($this->composer, $this->io, $this->options);
    }
}
