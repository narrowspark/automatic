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
use Narrowspark\Automatic\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\Contract\PackageConfigurator as PackageConfiguratorContract;
use Narrowspark\Automatic\Installer\ConfiguratorInstaller;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\Operation\Install;
use Narrowspark\Automatic\Operation\Uninstall;
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
            Config::class => static function (ContainerContract $container) {
                return $container->get(Composer::class)->getConfig();
            },
            IOInterface::class => static function () use ($io) {
                return $io;
            },
            'vendor-dir' => static function (ContainerContract $container) {
                return \rtrim($container->get(Config::class)->get('vendor-dir'), '/');
            },
            'composer-extra' => static function (ContainerContract $container) {
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
            InputInterface::class => static function (ContainerContract $container) use ($genericPropertyReader) {
                return $genericPropertyReader($container->get(IOInterface::class), 'input');
            },
            Lock::class => static function () {
                return new Lock(Automatic::getAutomaticLockFile());
            },
            ClassFinder::class => static function (ContainerContract $container) {
                return new ClassFinder($container->get('vendor-dir'));
            },
            ConfiguratorInstaller::class => static function (ContainerContract $container) {
                return new ConfiguratorInstaller(
                    $container->get(IOInterface::class),
                    $container->get(Composer::class),
                    $container->get(Lock::class),
                    $container->get(ClassFinder::class)
                );
            },
            SkeletonInstaller::class => static function (ContainerContract $container) {
                return new SkeletonInstaller(
                    $container->get(IOInterface::class),
                    $container->get(Composer::class),
                    $container->get(Lock::class),
                    $container->get(ClassFinder::class)
                );
            },
            ConfiguratorContract::class => static function (ContainerContract $container) {
                return new Configurator(
                    $container->get(Composer::class),
                    $container->get(IOInterface::class),
                    $container->get('composer-extra')
                );
            },
            PackageConfiguratorContract::class => static function (ContainerContract $container) {
                return new PackageConfigurator(
                    $container->get(Composer::class),
                    $container->get(IOInterface::class),
                    $container->get('composer-extra')
                );
            },
            Install::class => static function (ContainerContract $container) {
                return new Install(
                    $container->get('vendor-dir'),
                    $container->get(Lock::class),
                    $container->get(IOInterface::class),
                    $container->get(ConfiguratorContract::class),
                    $container->get(PackageConfiguratorContract::class),
                    $container->get(ClassFinder::class)
                );
            },
            Uninstall::class => static function (ContainerContract $container) {
                return new Uninstall(
                    $container->get('vendor-dir'),
                    $container->get(Lock::class),
                    $container->get(IOInterface::class),
                    $container->get(ConfiguratorContract::class),
                    $container->get(PackageConfiguratorContract::class),
                    $container->get(ClassFinder::class)
                );
            },
            RemoteFilesystem::class => static function (ContainerContract $container) {
                return Factory::createRemoteFilesystem(
                    $container->get(IOInterface::class),
                    $container->get(Config::class)
                );
            },
            ParallelDownloader::class => static function (ContainerContract $container) {
                $rfs = $container->get(RemoteFilesystem::class);

                return new ParallelDownloader(
                    $container->get(IOInterface::class),
                    $container->get(Config::class),
                    $rfs->getOptions(),
                    $rfs->isTlsDisabled()
                );
            },
            Prefetcher::class => static function (ContainerContract $container) {
                return new Prefetcher(
                    $container->get(Composer::class),
                    $container->get(IOInterface::class),
                    $container->get(InputInterface::class),
                    $container->get(ParallelDownloader::class)
                );
            },
            ScriptExecutor::class => static function (ContainerContract $container) {
                $scriptExecutor = new ScriptExecutor(
                    $container->get(Composer::class),
                    $container->get(IOInterface::class),
                    new ProcessExecutor(),
                    $container->get('composer-extra')
                );

                $scriptExecutor->add(ScriptExtender::getType(), ScriptExtender::class);
                $scriptExecutor->add(PhpScriptExtender::getType(), PhpScriptExtender::class);

                return $scriptExecutor;
            },
            LegacyTagsManager::class => static function (ContainerContract $container) {
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
