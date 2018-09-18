<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Util\ProcessExecutor;
use Composer\Util\RemoteFilesystem;
use Narrowspark\Automatic\Common\ClassFinder;
use Narrowspark\Automatic\Common\ScriptExtender\PhpScriptExtender;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\Installer\ConfiguratorInstaller;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\Prefetcher\ParallelDownloader;
use Narrowspark\Automatic\Prefetcher\Prefetcher;
use Narrowspark\Automatic\ScriptExtender\ScriptExtender;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
final class Container implements ContainerContract
{
    use GetGenericPropertyReaderTrait;

    /**
     * The array of closures defining each entry of the container.
     *
     * @var array<string, callable>
     */
    private $data;

    /**
     * The array of entries once they have been instantiated.
     *
     * @var array<string, mixed>
     */
    private $objects;

    /**
     * Instantiate the container.
     *
     * @param \Composer\Composer       $composer
     * @param \Composer\IO\IOInterface $io
     */
    public function __construct(Composer $composer, IOInterface $io)
    {
        $genericPropertyReader = $this->getGenericPropertyReader();

        $this->data = [
            Composer::class => static function () use ($composer) {
                return $composer;
            },
            Config::class => static function (Container $container) {
                return $container->get(Composer::class)->getConfig();
            },
            IOInterface::class => static function () use ($io) {
                return $io;
            },
            'vendor-dir' => static function (Container $container) {
                return \rtrim($container->get(Config::class)->get('vendor-dir'), '/');
            },
            'composer-extra' => static function (Container $container) {
                return \array_merge(
                    [
                        Automatic::COMPOSER_EXTRA_KEY => [
                            'allow-auto-install' => false,
                            'dont-discover'      => [],
                        ],
                    ],
                    $container->get(Composer::class)->getPackage()->getExtra()
                );
            },
            InputInterface::class => static function (Container $container) use ($genericPropertyReader) {
                return $genericPropertyReader($container->get(IOInterface::class), 'input');
            },
            Lock::class => static function () {
                return new Lock(Util::getAutomaticLockFile());
            },
            ClassFinder::class => static function (Container $container) {
                return new ClassFinder($container->get('vendor-dir'));
            },
            ConfiguratorInstaller::class => static function (Container $container) {
                return new ConfiguratorInstaller(
                    $container->get(IOInterface::class),
                    $container->get(Composer::class),
                    $container->get(Lock::class),
                    $container->get(ClassFinder::class)
                );
            },
            SkeletonInstaller::class => static function (Container $container) {
                return new SkeletonInstaller(
                    $container->get(IOInterface::class),
                    $container->get(Composer::class),
                    $container->get(Lock::class),
                    $container->get(ClassFinder::class)
                );
            },
            Configurator::class => static function (Container $container) {
                return new Configurator(
                    $container->get(Composer::class),
                    $container->get(IOInterface::class),
                    $container->get('composer-extra')
                );
            },
            OperationsResolver::class => static function (Container $container) {
                return new OperationsResolver(
                    $container->get(Lock::class),
                    $container->get('vendor-dir')
                );
            },
            RemoteFilesystem::class => static function (Container $container) {
                return Factory::createRemoteFilesystem(
                    $container->get(IOInterface::class),
                    $container->get(Config::class)
                );
            },
            ParallelDownloader::class => static function (Container $container) {
                $rfs = $container->get(RemoteFilesystem::class);

                return new ParallelDownloader(
                    $container->get(IOInterface::class),
                    $container->get(Config::class),
                    $rfs->getOptions(),
                    $rfs->isTlsDisabled()
                );
            },
            Prefetcher::class => static function (Container $container) {
                return new Prefetcher(
                    $container->get(Composer::class),
                    $container->get(IOInterface::class),
                    $container->get(InputInterface::class),
                    $container->get(ParallelDownloader::class)
                );
            },
            ScriptExecutor::class => static function (Container $container) {
                $scriptExecutor = new ScriptExecutor(
                    $container->get(Composer::class),
                    $container->get(IOInterface::class),
                    new ProcessExecutor(),
                    $container->get('composer-extra')
                );

                $scriptExecutor->addExtender(ScriptExtender::getType(), ScriptExtender::class);
                $scriptExecutor->addExtender(PhpScriptExtender::getType(), PhpScriptExtender::class);

                return $scriptExecutor;
            },
            PackageConfigurator::class => static function (Container $container) {
                return new PackageConfigurator(
                    $container->get(Composer::class),
                    $container->get(IOInterface::class),
                    $container->get('composer-extra')
                );
            },
            LegacyTagsManager::class => static function (Container $container) {
                return new LegacyTagsManager($container->get(IOInterface::class));
            },
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $id, callable $callback): void
    {
        $this->data[$id] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id)
    {
        if (isset($this->objects[$id])) {
            return $this->objects[$id];
        }

        if (! isset($this->data[$id])) {
            throw new InvalidArgumentException(\sprintf('Identifier [%s] is not defined.', $id));
        }

        return $this->objects[$id] = $this->data[$id]($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(): array
    {
        return $this->data;
    }
}
