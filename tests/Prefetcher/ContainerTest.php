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

namespace Narrowspark\Automatic\Test\Prefetcher;

use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Util\RemoteFilesystem;
use Mockery;
use Narrowspark\Automatic\Common\Downloader\ParallelDownloader;
use Narrowspark\Automatic\Prefetcher\Container;
use Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager as LegacyTagsManagerContract;
use Narrowspark\Automatic\Prefetcher\Contract\Prefetcher as PrefetcherContract;
use Narrowspark\Automatic\Prefetcher\LegacyTagsManager;
use Narrowspark\Automatic\Prefetcher\Prefetcher;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Prefetcher\Container
 *
 * @medium
 */
final class ContainerTest extends MockeryTestCase
{
    /** @var \Narrowspark\Automatic\Prefetcher\Container */
    private $container;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $composer = new Composer();

        /** @var \Composer\Config|\Mockery\MockInterface $configMock */
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
        $configMock->shouldReceive('get')
            ->with('cache-repo-dir')
            ->andReturn(__DIR__);

        $composer->setConfig($configMock);

        /** @var \Composer\Package\RootPackageInterface|\Mockery\MockInterface $package */
        $package = Mockery::mock(RootPackageInterface::class);
        $package->shouldReceive('getExtra')
            ->andReturn([]);

        $composer->setPackage($package);

        /** @var \Composer\Downloader\DownloadManager|\Mockery\MockInterface $downloadManager */
        $downloadManager = Mockery::mock(DownloadManager::class);
        $downloadManager->shouldReceive('getDownloader')
            ->with('file');

        $composer->setDownloadManager($downloadManager);

        $this->container = new Container($composer, new BufferIO());
    }

    /**
     * @dataProvider provideContainerInstancesCases
     *
     * @param class-string<object>|mixed[] $expected
     */
    public function testContainerInstances(string $key, $expected): void
    {
        $value = $this->container->get($key);

        if (\is_string($value) || (\is_array($value) && \is_array($expected))) {
            self::assertSame($expected, $value);
        }

        if (\is_object($value) && \is_string($expected)) {
            self::assertInstanceOf($expected, $value);
        }
    }

    /**
     * @return array<int, array<int|string, mixed>|string>
     */
    public static function provideContainerInstancesCases(): iterable
    {
        return [
            [Composer::class, Composer::class],
            [Config::class, Config::class],
            [IOInterface::class, BufferIO::class],
            [InputInterface::class, InputInterface::class],
            [RemoteFilesystem::class, RemoteFilesystem::class],
            [ParallelDownloader::class, ParallelDownloader::class],
            [PrefetcherContract::class, Prefetcher::class],
            [LegacyTagsManagerContract::class, LegacyTagsManager::class],
            ['composer-extra', []],
        ];
    }

    public function testGetAll(): void
    {
        self::assertCount(9, $this->container->getAll());
    }
}
