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

namespace Narrowspark\Automatic\Tests\Prefetcher;

use Composer\Installer\InstallerEvent;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Util\RemoteFilesystem;
use Mockery;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Common\Downloader\ParallelDownloader;
use Narrowspark\Automatic\Prefetcher\Contract\Prefetcher as PrefetcherContract;
use Narrowspark\Automatic\Prefetcher\FunctionMock;
use Narrowspark\Automatic\Prefetcher\Plugin;
use Narrowspark\Automatic\Tests\Traits\ArrangeComposerClassesTrait;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Nyholm\NSA;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Prefetcher\Plugin
 *
 * @medium
 */
final class PluginTest extends MockeryTestCase
{
    use ArrangeComposerClassesTrait;

    /** @var \Narrowspark\Automatic\Prefetcher\Plugin */
    private $plugin;

    /** @var \Mockery\MockInterface|\Narrowspark\Automatic\Common\Contract\Container */
    private $containerMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->arrangeComposerClasses();

        $this->plugin = new Plugin();

        $this->containerMock = Mockery::mock(ContainerContract::class);

        NSA::setProperty($this->plugin, 'container', $this->containerMock);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        FunctionMock::$isOpensslActive = true;
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
        $event = Mockery::mock(InstallerEvent::class);

        $prefetcher = Mockery::mock(PrefetcherContract::class);
        $prefetcher->shouldReceive('fetchAllFromOperations')
            ->once()
            ->with($event);

        $this->containerMock->shouldReceive('get')
            ->once()
            ->with(PrefetcherContract::class)
            ->andReturn($prefetcher);

        $this->plugin->populateFilesCacheDir($event);
    }

    public function testOnFileDownload(): void
    {
        $remoteFilesystem = Mockery::mock(RemoteFilesystem::class);
        $remoteFilesystem->shouldReceive('getOptions')
            ->once()
            ->andReturn([]);

        $event = Mockery::mock(PreFileDownloadEvent::class);
        $event->shouldReceive('getRemoteFilesystem')
            ->twice()
            ->andReturn($remoteFilesystem);

        $downloader = Mockery::mock(ParallelDownloader::class);
        $downloader->shouldReceive('setNextOptions')
            ->once()
            ->with([]);

        $event->shouldReceive('setRemoteFilesystem')
            ->once()
            ->with(Mockery::type(ParallelDownloader::class));

        $this->containerMock->shouldReceive('get')
            ->once()
            ->with(ParallelDownloader::class)
            ->andReturn($downloader);

        $this->plugin->onFileDownload($event);
    }

    public function testActivateWithNoOpenssl(): void
    {
        FunctionMock::$isOpensslActive = false;

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('<warning>Narrowspark Automatic Prefetcher has been disabled. You must enable the openssl extension in your [php.ini] file</warning>');

        $this->plugin->activate($this->composerMock, $this->ioMock);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods(bool $allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
