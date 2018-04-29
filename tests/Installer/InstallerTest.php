<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Installer;

use Composer\Autoload\AutoloadGenerator;
use Composer\Downloader\DownloadManager;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer as BaseInstaller;
use Composer\Installer\InstallationManager;
use Composer\Package\Locker;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use Narrowspark\Discovery\Installer\Installer;

class InstallerTest extends AbstractInstallerTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

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

    protected function arrangeInput(): void
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
            ->with('no-suggest')
            ->andReturn(false);
    }
}
