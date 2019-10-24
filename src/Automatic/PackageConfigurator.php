<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\Automatic;

use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Contract\PackageConfigurator as PackageConfiguratorContract;

final class PackageConfigurator extends AbstractConfigurator implements PackageConfiguratorContract
{
    /**
     * Get a package configurator.
     *
     * @param string $key
     *
     * @return \Narrowspark\Automatic\Common\Contract\Configurator
     */
    protected function get(string $key): ConfiguratorContract
    {
        $class = $this->configurators[$key];

        return new $class($this->composer, $this->io, $this->options);
    }
}
