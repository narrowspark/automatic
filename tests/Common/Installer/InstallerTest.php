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

namespace Narrowspark\Automatic\Test\Common\Installer;

use Composer\Autoload\AutoloadGenerator;
use Composer\Downloader\DownloadManager;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer as BaseInstaller;
use Composer\Installer\InstallationManager;
use Composer\Package\Locker;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use Narrowspark\Automatic\Common\Installer\Installer;
use Narrowspark\Automatic\Test\Traits\ArrangeComposerClasses;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 *
 * @small
 */
final class InstallerTest extends MockeryTestCase
{
    use ArrangeComposerClasses;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->arrangeComposerClasses();

        $this->composerMock->shouldReceive('getLocker')
            ->once()
            ->andReturn($this->mock(Locker::class));
        $this->composerMock->shouldReceive('getEventDispatcher')
            ->once()
            ->andReturn($this->mock(EventDispatcher::class));
        $this->composerMock->shouldReceive('getAutoloadGenerator')
            ->once()
            ->andReturn($this->mock(AutoloadGenerator::class));
        $this->composerMock->shouldReceive('getDownloadManager')
            ->once()
            ->andReturn($this->mock(DownloadManager::class));

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($this->mock(RootPackageInterface::class));
        $this->composerMock->shouldReceive('getRepositoryManager')
            ->once()
            ->andReturn($this->mock(RepositoryManager::class));

        $installationManager = $this->mock(InstallationManager::class);
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

        $installer = Installer::create($this->ioMock, $this->composerMock, $this->inputMock);

        self::assertInstanceOf(BaseInstaller::class, $installer);
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

    /**
     * @param bool        $optimize
     * @param bool        $classmap
     * @param null|string $preferred
     */
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

        $this->composerMock->shouldReceive('getConfig')
            ->andReturn($this->configMock);
    }
}
