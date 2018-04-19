<?php
declare(strict_types=1);
namespace Narrowspark\Discovery;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Discovery\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Discovery\Common\Contract\Package as PackageContract;
use Narrowspark\Discovery\Common\Exception\InvalidArgumentException;

final class PackageConfigurator
{
    /**
     * A composer instance.
     *
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * A array of project options.
     *
     * @var array
     */
    private $options;

    /**
     * Found package configurators.
     *
     * @var array
     */
    private $configurators = [];

    /**
     * Create a new Configurator class.
     *
     * @param \Composer\Composer       $composer
     * @param \Composer\IO\IOInterface $io
     * @param array                    $options
     * @param array                    $configurators
     */
    public function __construct(Composer $composer, IOInterface $io, array $options, array $configurators)
    {
        $this->composer = $composer;
        $this->io       = $io;
        $this->options  = $options;

        foreach ($configurators as $name => $configurator) {
            $this->add($name, $configurator);
        }
    }

    /**
     * Add a new discovery configurator.
     *
     * @param string $name
     * @param string $configurator
     *
     * @throws \Narrowspark\Discovery\Common\Exception\InvalidArgumentException
     *
     * @return void
     */
    public function add(string $name, string $configurator): void
    {
        if (! \is_subclass_of($configurator, ConfiguratorContract::class)) {
            throw new InvalidArgumentException(\sprintf('Configurator class "%s" must extend the class "%s".', $configurator, ConfiguratorContract::class));
        }

        $this->configurators[$name] = $configurator;
    }

    /**
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
     *
     * @return void
     */
    public function configure(PackageContract $package): void
    {
        foreach (\array_keys($this->configurators) as $key) {
            if ($package->hasConfiguratorKey($key)) {
                $this->get($key)->configure($package);
            }
        }
    }

    /**
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
     *
     * @return void
     */
    public function unconfigure(PackageContract $package): void
    {
        foreach (\array_keys($this->configurators) as $key) {
            if ($package->hasConfiguratorKey($key)) {
                $this->get($key)->unconfigure($package);
            }
        }
    }

    /**
     * Get a package configurator.
     *
     * @param string $key
     *
     * @return \Narrowspark\Discovery\Common\Contract\Configurator
     */
    private function get(string $key): ConfiguratorContract
    {
        $class = $this->configurators[$key];

        return new $class($this->composer, $this->io, $this->options);
    }
}
