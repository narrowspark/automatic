<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Prefetcher\Test;

use Composer\Installer\InstallerEvent;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Util\RemoteFilesystem;
use Narrowspark\Automatic\Prefetcher\ParallelDownloader;
use Narrowspark\Automatic\Prefetcher\Plugin;
use Narrowspark\Automatic\Prefetcher\Prefetcher;
use Narrowspark\Automatic\Test\Traits\ArrangeComposerClasses;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Nyholm\NSA;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;

/**
 * @internal
 *
 * @small
 */
final class PluginTest extends MockeryTestCase
{
    use ArrangeComposerClasses;

    /** @var \Narrowspark\Automatic\Prefetcher\Plugin */
    private $plugin;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->arrangeComposerClasses();

        $this->plugin = new class() extends Plugin
        {
            public function setContainer($container): void
            {
                $this->container = $container;
            }
        };
    }

    public function testGetSubscribedEvents(): void
    {
        NSA::setProperty($this->plugin, 'activated', true);

        self::assertCount(5, Plugin::getSubscribedEvents());

        NSA::setProperty($this->plugin, 'activated', false);

        self::assertCount(0, Plugin::getSubscribedEvents());
    }

    public function testPopulateFilesCacheDir(): void
    {
        $event = $this->mock(InstallerEvent::class);

        $prefetcher = $this->mock(Prefetcher::class);
        $prefetcher->shouldReceive('fetchAllFromOperations')
            ->once()
            ->with($event);

        $containerMock = $this->mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->once()
            ->with(Prefetcher::class)
            ->andReturn($prefetcher);

        $this->plugin->setContainer($containerMock);
        $this->plugin->populateFilesCacheDir($event);
    }

    public function testOnFileDownload(): void
    {
        $remoteFilesystem = $this->mock(RemoteFilesystem::class);
        $remoteFilesystem->shouldReceive('getOptions')
            ->once()
            ->andReturn([]);

        $event = $this->mock(PreFileDownloadEvent::class);
        $event->shouldReceive('getRemoteFilesystem')
            ->twice()
            ->andReturn($remoteFilesystem);

        $downloader = $this->mock(ParallelDownloader::class);
        $downloader->shouldReceive('setNextOptions')
            ->once()
            ->with([]);

        $event->shouldReceive('setRemoteFilesystem')
            ->once()
            ->with(\Mockery::type(ParallelDownloader::class));

        $containerMock = $this->mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->once()
            ->with(ParallelDownloader::class)
            ->andReturn($downloader);

        $this->plugin->setContainer($containerMock);
        $this->plugin->onFileDownload($event);
    }

}