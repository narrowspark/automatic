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

namespace Narrowspark\Automatic\Test\Common\Installer;

use Composer\Autoload\AutoloadGenerator;
use Composer\Downloader\DownloadManager;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallationManager;
use Composer\Package\Locker;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use Mockery;
use Narrowspark\Automatic\Common\Installer\Installer;
use Narrowspark\Automatic\Test\Traits\ArrangeComposerClassesTrait;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Common\Installer\Installer
 *
 * @medium
 */
final class InstallerTest extends MockeryTestCase
{
    use ArrangeComposerClassesTrait;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->arrangeComposerClasses();

        $this->composerMock->shouldReceive('getLocker')
            ->once()
            ->andReturn(Mockery::mock(Locker::class));
        $this->composerMock->shouldReceive('getEventDispatcher')
            ->once()
            ->andReturn(Mockery::mock(EventDispatcher::class));
        $this->composerMock->shouldReceive('getAutoloadGenerator')
            ->once()
            ->andReturn(Mockery::mock(AutoloadGenerator::class));
        $this->composerMock->shouldReceive('getDownloadManager')
            ->once()
            ->andReturn(Mockery::mock(DownloadManager::class));

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn(Mockery::mock(RootPackageInterface::class));
        $this->composerMock->shouldReceive('getRepositoryManager')
            ->once()
            ->andReturn(Mockery::mock(RepositoryManager::class));

        $installationManager = Mockery::mock(InstallationManager::class);
        $installationManager->shouldReceive('disablePlugins')
            ->once();

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManager);
    }

    public function testCreateWithConfigSettings(): void
    {
        $this->setupInstallerConfig(true, true, 'auto');
        $this->arrangeInput();

        Installer::create($this->ioMock, $this->composerMock, $this->inputMock);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    private function arrangeInput(): void
    {
        $this->inputMock->shouldReceive('hasOption')
            ->once()
            ->with('prefer-source')
            ->andReturn(false);
        $this->inputMock->shouldReceive('hasOption')
            ->once()
            ->with('prefer-dist')
            ->andReturn(false);
        $this->inputMock->shouldReceive('hasOption')
            ->once()
            ->with('optimize-autoloader')
            ->andReturn(false);
        $this->inputMock->shouldReceive('hasOption')
            ->once()
            ->with('classmap-authoritative')
            ->andReturn(false);
        $this->inputMock->shouldReceive('hasOption')
            ->once()
            ->with('dry-run')
            ->andReturn(true);
        $this->inputMock->shouldReceive('getOption')
            ->once()
            ->with('dry-run')
            ->andReturn(true);
        $this->inputMock->shouldReceive('hasOption')
            ->once()
            ->with('verbose')
            ->andReturn(false);
        $this->inputMock->shouldReceive('hasOption')
            ->once()
            ->with('no-dev')
            ->andReturn(false);
        $this->inputMock->shouldReceive('hasOption')
            ->once()
            ->with('apcu-autoloader')
            ->andReturn(false);
        $this->inputMock->shouldReceive('hasOption')
            ->once()
            ->with('no-autoloader')
            ->andReturn(false);
        $this->inputMock->shouldReceive('hasOption')
            ->once()
            ->with('no-scripts')
            ->andReturn(false);
        $this->inputMock->shouldReceive('hasOption')
            ->once()
            ->with('ignore-platform-reqs')
            ->andReturn(false);
    }

    private function setupInstallerConfig(bool $optimize, bool $classmap, ?string $preferred): void
    {
        $this->configMock->shouldReceive('get')
            ->with('optimize-autoloader')
            ->once()
            ->andReturn($optimize);
        $this->configMock->shouldReceive('get')
            ->with('classmap-authoritative')
            ->once()
            ->andReturn($classmap);
        $this->configMock->shouldReceive('get')
            ->with('preferred-install')
            ->once()
            ->andReturn($preferred);
        $this->configMock->shouldReceive('get')
            ->with('apcu-autoloader')
            ->once()
            ->andReturn(false);
        $this->configMock->shouldReceive('get')
            ->with('lock')
            ->zeroOrMoreTimes()
            ->andReturn('');

        $this->composerMock->shouldReceive('getConfig')
            ->andReturn($this->configMock);
    }
}
