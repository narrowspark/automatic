<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;

final class PackageConfigurator extends AbstractConfigurator
{
    public const TYPE = 'custom-configurators';

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
