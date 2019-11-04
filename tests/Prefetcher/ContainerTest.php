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

namespace Narrowspark\Automatic\Test\Prefetcher;

use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Util\RemoteFilesystem;
use Mockery;
use Narrowspark\Automatic\Prefetcher\Container;
use Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager as LegacyTagsManagerContract;
use Narrowspark\Automatic\Prefetcher\Downloader\ParallelDownloader;
use Narrowspark\Automatic\Prefetcher\LegacyTagsManager;
use Narrowspark\Automatic\Prefetcher\Prefetcher;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Symfony\Component\Console\Input\InputInterface;
use function is_array;
use function is_string;

/**
 * @internal
 *
 * @small
 *
 * @covers \Narrowspark\Automatic\Prefetcher\Container
 */
final class ContainerTest extends MockeryTestCase
{
    /** @var \Narrowspark\Automatic\Prefetcher\Container */
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
            [InputInterface::class, InputInterface::class],
            [RemoteFilesystem::class, RemoteFilesystem::class],
            [ParallelDownloader::class, ParallelDownloader::class],
            [Prefetcher::class, Prefetcher::class],
            [LegacyTagsManagerContract::class, LegacyTagsManager::class],
            ['composer-extra', []],
        ];
    }

    public function testGetAll(): void
    {
        self::assertCount(9, self::$staticContainer->getAll());
    }
}
