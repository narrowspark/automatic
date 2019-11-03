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

namespace Narrowspark\Automatic\Test;

use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Mockery;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\ClassFinder;
use Narrowspark\Automatic\Configurator;
use Narrowspark\Automatic\Container;
use Narrowspark\Automatic\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Contract\PackageConfigurator as PackageConfiguratorContract;
use Narrowspark\Automatic\Installer\ConfiguratorInstaller;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\Lock;
use Narrowspark\Automatic\Operation\Install;
use Narrowspark\Automatic\Operation\Uninstall;
use Narrowspark\Automatic\PackageConfigurator;
use Narrowspark\Automatic\ScriptExecutor;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;
use function is_array;
use function is_string;

/**
 * @internal
 *
 * @small
 */
final class ContainerTest extends MockeryTestCase
{
    /** @var \Narrowspark\Automatic\Container */
    private static $staticContainer;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $composer = new Composer();
        $configMock = Mockery::mock(Config::class);
        $configMock->shouldReceive('get')
            ->with('vendor-dir')
            ->andReturn('/vendor');
        $configMock->shouldReceive('get')
            ->with('cache-files-dir')
            ->andReturn('');
        $configMock->shouldReceive('get')
            ->with('disable-tls')
            ->andReturn(true);
        $configMock->shouldReceive('get')
            ->with('bin-dir')
            ->andReturn(__DIR__);
        $configMock->shouldReceive('get')
            ->with('bin-compat')
            ->andReturn(__DIR__);

        $composer->setConfig($configMock);

        $package = Mockery::mock(RootPackageInterface::class);
        $package->shouldReceive('getExtra')
            ->andReturn([]);

        $composer->setPackage($package);

        $downloadManager = Mockery::mock(DownloadManager::class);
        $downloadManager->shouldReceive('getDownloader')
            ->with('file');

        $composer->setDownloadManager($downloadManager);

        self::$staticContainer = new Container($composer, new BufferIO());
    }

    /**
     * @dataProvider provideContainerInstancesCases
     *
     * @param string $key
     * @param mixed  $expected
     *
     * @return void
     */
    public function testContainerInstances(string $key, $expected): void
    {
        $value = self::$staticContainer->get($key);

        if (is_string($value) || is_array($value)) {
            self::assertSame($expected, $value);
        } else {
            self::assertInstanceOf($expected, $value);
        }
    }

    /**
     * @return array
     */
    public function provideContainerInstancesCases(): iterable
    {
        return [
            [Composer::class, Composer::class],
            [Config::class, Config::class],
            [IOInterface::class, BufferIO::class],
            ['vendor-dir', '/vendor'],
            [
                'composer-extra',
                [
                    Automatic::COMPOSER_EXTRA_KEY => [
                        'allow-auto-install' => false,
                        'dont-discover' => [],
                    ],
                ],
            ],
            [InputInterface::class, InputInterface::class],
            [Lock::class, Lock::class],
            [ClassFinder::class, ClassFinder::class],
            [ConfiguratorInstaller::class, ConfiguratorInstaller::class],
            [SkeletonInstaller::class, SkeletonInstaller::class],
            [ConfiguratorContract::class, Configurator::class],
            [Install::class, Install::class],
            [Uninstall::class, Uninstall::class],
            [ScriptExecutor::class, ScriptExecutor::class],
            [PackageConfiguratorContract::class, PackageConfigurator::class],
            [Filesystem::class, Filesystem::class],
        ];
    }

    public function testGetAll(): void
    {
        self::assertCount(16, self::$staticContainer->getAll());
    }
}
