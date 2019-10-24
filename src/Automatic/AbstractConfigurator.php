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

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Contract\Configurator as MainConfiguratorContract;

abstract class AbstractConfigurator implements MainConfiguratorContract
{
    /**
     * All registered automatic configurators.
     *
     * @var array
     */
    protected $configurators = [];

    /**
     * A composer instance.
     *
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * A array of project options.
     *
     * @var array
     */
    protected $options;

    /**
     * Create a new Configurator class.
     *
     * @param \Composer\Composer       $composer
     * @param \Composer\IO\IOInterface $io
     * @param array                    $options
     */
    public function __construct(Composer $composer, IOInterface $io, array $options)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurators(): array
    {
        return $this->configurators;
    }

    /**
     * {@inheritdoc}
     */
    public function add(string $name, string $configurator): void
    {
        if ($this->has($name)) {
            throw new InvalidArgumentException(\sprintf('Configurator with the name [%s] already exists.', $name));
        }

        if (! \is_subclass_of($configurator, ConfiguratorContract::class)) {
            throw new InvalidArgumentException(\sprintf('The class [%s] must implement the interface [\\%s].', $configurator, ConfiguratorContract::class));
        }

        $this->configurators[$name] = $configurator;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return isset($this->configurators[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
        foreach (\array_keys($this->configurators) as $key) {
            if ($package->hasConfig(ConfiguratorContract::TYPE, $key)) {
                $this->get($key)->configure($package);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
        foreach (\array_keys($this->configurators) as $key) {
            if ($package->hasConfig(ConfiguratorContract::TYPE, $key)) {
                $this->get($key)->unconfigure($package);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->configurators = [];
    }

    /**
     * Get a configurator.
     *
     * @param string $key
     *
     * @return \Narrowspark\Automatic\Common\Contract\Configurator
     */
    abstract protected function get(string $key): ConfiguratorContract;
}
