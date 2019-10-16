<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Prefetcher\Test;

use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\IO\BufferIO;
use Composer\Package\RootPackageInterface;
use Narrowspark\Automatic\Prefetcher\Container;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 *
 * @small
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
        $configMock = \Mockery::mock(Config::class);
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

        $package = \Mockery::mock(RootPackageInterface::class);
        $package->shouldReceive('getExtra')
            ->andReturn([]);

        $composer->setPackage($package);

        $downloadManager = \Mockery::mock(DownloadManager::class);
        $downloadManager->shouldReceive('getDownloader')
            ->with('file');

        $composer->setDownloadManager($downloadManager);

        self::$staticContainer = new Container($composer, new BufferIO());
    }
}
