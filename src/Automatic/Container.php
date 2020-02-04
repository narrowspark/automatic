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

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Util\ProcessExecutor;
use Narrowspark\Automatic\Common\AbstractContainer;
use Narrowspark\Automatic\Common\ClassFinder;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Common\ScriptExtender\PhpScriptExtender;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Contract\PackageConfigurator as PackageConfiguratorContract;
use Narrowspark\Automatic\Installer\ConfiguratorInstaller;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\Operation\Install;
use Narrowspark\Automatic\Operation\Uninstall;
use Narrowspark\Automatic\ScriptExtender\ScriptExtender;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final class Container extends AbstractContainer
{
    use GetGenericPropertyReaderTrait;

    /**
     * Instantiate the container.
     */
    public function __construct(Composer $composer, IOInterface $io)
    {
        $genericPropertyReader = $this->getGenericPropertyReader();

        parent::__construct([
            Composer::class => static function () use ($composer): Composer {
                return $composer;
            },
            Config::class => static function (ContainerContract $container): Config {
                return $container->get(Composer::class)->getConfig();
            },
            IOInterface::class => static function () use ($io): IOInterface {
                return $io;
            },
            'vendor-dir' => static function (ContainerContract $container): string {
                return \rtrim($container->get(Config::class)->get('vendor-dir'), '/');
            },
            'composer-extra' => static function (ContainerContract $container): array {
                return \array_merge(
                    [
                        Automatic::COMPOSER_EXTRA_KEY => [
                            'allow-auto-install' => false,
                            'dont-discover' => [],
                        ],
                    ],
                    $container->get(Composer::class)->getPackage()->getExtra()
                );
            },
            InputInterface::class => static function (ContainerContract $container) use ($genericPropertyReader): InputInterface {
                return $genericPropertyReader($container->get(IOInterface::class), 'input');
            },
            Lock::class => static function (): Lock {
                return new Lock(Automatic::getAutomaticLockFile());
            },
            ClassFinder::class => static function (ContainerContract $container): ClassFinder {
                return new ClassFinder($container->get('vendor-dir'));
            },
            ConfiguratorInstaller::class => static function (ContainerContract $container): ConfiguratorInstaller {
                return new ConfiguratorInstaller(
                    $container->get(IOInterface::class),
                    $container->get(Composer::class),
                    $container->get(Lock::class),
                    $container->get(ClassFinder::class)
                );
            },
            SkeletonInstaller::class => static function (ContainerContract $container): SkeletonInstaller {
                return new SkeletonInstaller(
                    $container->get(IOInterface::class),
                    $container->get(Composer::class),
                    $container->get(Lock::class),
                    $container->get(ClassFinder::class)
                );
            },
            ConfiguratorContract::class => static function (ContainerContract $container): ConfiguratorContract {
                return new Configurator(
                    $container->get(Composer::class),
                    $container->get(IOInterface::class),
                    $container->get('composer-extra')
                );
            },
            PackageConfiguratorContract::class => static function (ContainerContract $container): PackageConfiguratorContract {
                return new PackageConfigurator(
                    $container->get(Composer::class),
                    $container->get(IOInterface::class),
                    $container->get('composer-extra')
                );
            },
            Install::class => static function (ContainerContract $container): Install {
                return new Install(
                    $container->get('vendor-dir'),
                    $container->get(Lock::class),
                    $container->get(IOInterface::class),
                    $container->get(ConfiguratorContract::class),
                    $container->get(PackageConfiguratorContract::class),
                    $container->get(ClassFinder::class)
                );
            },
            Uninstall::class => static function (ContainerContract $container): Uninstall {
                return new Uninstall(
                    $container->get('vendor-dir'),
                    $container->get(Lock::class),
                    $container->get(IOInterface::class),
                    $container->get(ConfiguratorContract::class),
                    $container->get(PackageConfiguratorContract::class),
                    $container->get(ClassFinder::class)
                );
            },
            ScriptExecutor::class => static function (ContainerContract $container): ScriptExecutor {
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
            Filesystem::class => static function (): Filesystem {
                return new Filesystem();
            },
        ]);
    }
}
