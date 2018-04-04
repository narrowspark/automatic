<?php
declare(strict_types=1);
namespace Narrowspark\Discovery;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Discovery\Configurator\AbstractConfigurator;
use Narrowspark\Discovery\Configurator\ComposerScriptsConfigurator;
use Narrowspark\Discovery\Configurator\CopyFromPackageConfigurator;
use Narrowspark\Discovery\Configurator\EnvConfigurator;
use Narrowspark\Discovery\Configurator\GitIgnoreConfigurator;
use Narrowspark\Discovery\Configurator\ServiceProviderConfigurator;

final class Configurator
{
    /**
     * @var array
     */
    public static $configurators = [
        'composer_script' => ComposerScriptsConfigurator::class,
        'copy'            => CopyFromPackageConfigurator::class,
        'env'             => EnvConfigurator::class,
        'gitignore'       => GitIgnoreConfigurator::class,
        'providers'       => ServiceProviderConfigurator::class,
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
     * @return void
     */
    public function add(string $name, string $configurator): void
    {
        if (isset(self::$configurators[$name])) {
            throw new \InvalidArgumentException(sprintf('Configurator with the name "%s" already exists.', $name));
        }

        if (! \is_subclass_of($configurator, AbstractConfigurator::class)) {
            throw new \InvalidArgumentException(\sprintf('Configurator class "%s" must extend the class "%s".', $configurator, AbstractConfigurator::class));
        }

        static::$configurators[$name] = $configurator;
    }

    /**
     * @param \Narrowspark\Discovery\Package $package
     *
     * @return void
     */
    public function configure(Package $package): void
    {
        foreach (\array_keys(self::$configurators) as $key) {
            if ($package->hasConfiguratorKey($key, Package::CONFIGURE)) {
                $this->get($key)->configure($package);
            }
        }
    }

    /**
     * @param \Narrowspark\Discovery\Package $package
     *
     * @return void
     */
    public function unconfigure(Package $package): void
    {
        foreach (array_keys(self::$configurators) as $key) {
            if ($package->hasConfiguratorKey($key, Package::UNCONFIGURE)) {
                $this->get($key)->unconfigure($package);
            }
        }
    }

    /**
     * @param string $key
     *
     * @return \Narrowspark\Discovery\Configurator\AbstractConfigurator
     */
    private function get(string $key): AbstractConfigurator
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $class = self::$configurators[$key];

        return $this->cache[$key] = new $class($this->composer, $this->io, $this->options);
    }
}
