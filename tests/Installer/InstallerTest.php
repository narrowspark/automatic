<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Installer;

use Composer\Autoload\AutoloadGenerator;
use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer as BaseInstaller;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\Locker;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use Narrowspark\Discovery\Installer\Installer;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Symfony\Component\Console\Input\InputInterface;

class InstallerTest extends MockeryTestCase
{
    /**
     * @var \Composer\Composer|\Mockery\MockInterface
     */
    private $composerMock;

    /**
     * @var \Composer\IO\IOInterface|\Mockery\MockInterface
     */
    private $ioMock;

    /**
     * @var \Mockery\MockInterface|\Symfony\Component\Console\Input\InputInterface
     */
    private $inputMock;

    /**
     * @var \Composer\Config|\Mockery\MockInterface
     */
    private $configMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composerMock = $this->mock(Composer::class);
        $this->configMock   = $this->mock(Config::class);

        $this->composerMock->shouldReceive('getConfig')
            ->twice()
            ->andReturn($this->configMock);

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($this->mock(RootPackageInterface::class));
        $this->composerMock->shouldReceive('getDownloadManager')
            ->once()
            ->andReturn($this->mock(DownloadManager::class));
        $this->composerMock->shouldReceive('getRepositoryManager')
            ->once()
            ->andReturn($this->mock(RepositoryManager::class));
        $this->composerMock->shouldReceive('getLocker')
            ->once()
            ->andReturn($this->mock(Locker::class));

        $installationManager = $this->mock(InstallationManager::class);
        $installationManager->shouldReceive('disablePlugins')
            ->once();

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManager);
        $this->composerMock->shouldReceive('getEventDispatcher')
            ->once()
            ->andReturn($this->mock(EventDispatcher::class));
        $this->composerMock->shouldReceive('getAutoloadGenerator')
            ->once()
            ->andReturn($this->mock(AutoloadGenerator::class));

        $this->ioMock    = $this->mock(IOInterface::class);
        $this->inputMock = $this->mock(InputInterface::class);
    }

    public function testCreateWithConfigSettings(): void
    {
        $this->configMock->shouldReceive('get')
            ->with('optimize-autoloader')
            ->once()
            ->andReturn(true);
        $this->configMock->shouldReceive('get')
            ->with('classmap-authoritative')
            ->once()
            ->andReturn(true);
        $this->configMock->shouldReceive('get')
            ->with('preferred-install')
            ->once()
            ->andReturn('auto');

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
}
