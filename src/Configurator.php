<?php
declare(strict_types=1);
namespace Narrowspark\Discovery;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Discovery\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Discovery\Common\Contract\Package as PackageContract;
use Narrowspark\Discovery\Common\Exception\InvalidArgumentException;
use Narrowspark\Discovery\Configurator\ComposerScriptsConfigurator;
use Narrowspark\Discovery\Configurator\CopyFromPackageConfigurator;
use Narrowspark\Discovery\Configurator\EnvConfigurator;
use Narrowspark\Discovery\Configurator\GitIgnoreConfigurator;
use Narrowspark\Discovery\Configurator\ProxyConfigurator;
use Narrowspark\Discovery\Configurator\ServiceProviderConfigurator;

final class Configurator
{
    /**
     * @var array
     */
    public static $configurators = [
        'composer-scripts' => ComposerScriptsConfigurator::class,
        'copy'             => CopyFromPackageConfigurator::class,
        'env'              => EnvConfigurator::class,
        'gitignore'        => GitIgnoreConfigurator::class,
        'providers'        => ServiceProviderConfigurator::class,
        'proxies'          => ProxyConfigurator::class,
    ];

    /**
     * Cache found configurators form manifest.
     *
     * @var array
     */
    private $cache = [];

    /**
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * @var array
     */
    private $options;

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
        $this->io       = $io;
        $this->options  = $options;
    }

    /**
     * Add a new Discovery Configurator.
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
        if (isset(self::$configurators[$name])) {
            throw new InvalidArgumentException(\sprintf('Configurator with the name "%s" already exists.', $name));
        }

        if (! \is_subclass_of($configurator, ConfiguratorContract::class)) {
            throw new InvalidArgumentException(\sprintf('Configurator class "%s" must extend the class "%s".', $configurator, ConfiguratorContract::class));
        }

        static::$configurators[$name] = $configurator;
    }

    /**
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
     *
     * @return void
     */
    public function configure(PackageContract $package): void
    {
        foreach (\array_keys(self::$configurators) as $key) {
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
        foreach (\array_keys(self::$configurators) as $key) {
            if ($package->hasConfiguratorKey($key)) {
                $this->get($key)->unconfigure($package);
            }
        }
    }

    /**
     * @param string $key
     *
     * @return \Narrowspark\Discovery\Common\Contract\Configurator
     */
    private function get(string $key): ConfiguratorContract
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $class = self::$configurators[$key];

        return $this->cache[$key] = new $class($this->composer, $this->io, $this->options);
    }
}
