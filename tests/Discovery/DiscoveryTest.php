<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Composer\Composer;
use Composer\Downloader\DownloaderInterface;
use Composer\Downloader\DownloadManager;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginManager;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use Narrowspark\Discovery\Configurator;
use Narrowspark\Discovery\Discovery;
use Narrowspark\Discovery\Installer\ConfiguratorInstaller;
use Narrowspark\Discovery\Lock;
use Narrowspark\Discovery\Test\Traits\ArrangeComposerClasses;
use Narrowspark\Discovery\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;

class DiscoveryTest extends MockeryTestCase
{
    use GetGenericPropertyReaderTrait;
    use ArrangeComposerClasses;

    /**
     * @var \Narrowspark\Discovery\Discovery
     */
    private $discovery;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->composerCachePath = __DIR__ . '/' . __CLASS__;

        \mkdir($this->composerCachePath);
        \putenv('COMPOSER_CACHE_DIR=' . $this->composerCachePath);

        $this->arrangeComposerClasses();

        $this->discovery = new Discovery();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        \putenv('COMPOSER_CACHE_DIR=');
        \putenv('COMPOSER_CACHE_DIR');

        (new Filesystem())->remove($this->composerCachePath);
    }

    public function testGetDiscoveryLockFile(): void
    {
        self::assertSame('./discovery.lock', Discovery::getDiscoveryLockFile());
    }

    public function testGetComposerJsonFileAndManipulator(): void
    {
        [$json, $manipulator] = Discovery::getComposerJsonFileAndManipulator();

        self::assertInstanceOf(JsonFile::class, $json);
        self::assertInstanceOf(JsonManipulator::class, $manipulator);
    }

    public function testGetSubscribedEvents(): void
    {
        self::assertCount(13, Discovery::getSubscribedEvents());
    }

    public function testActivate(): void
    {
        $this->arrangeDiscoveryConfig();

        $this->arrangePackagist();

        $rootPackageMock = $this->mock(RootPackageInterface::class);
        $rootPackageMock->shouldReceive('getExtra')
            ->andReturn([]);
        $rootPackageMock->shouldReceive('getMinimumStability')
            ->once()
            ->andReturn('stable');

        $this->composerMock->shouldReceive('getPackage')
            ->twice()
            ->andReturn($rootPackageMock);

        $localRepositoryMock = $this->mock(WritableRepositoryInterface::class);
        $localRepositoryMock->shouldReceive('getPackages')
            ->once()
            ->andReturn([]);

        $repositoryMock = $this->mock(RepositoryManager::class);
        $repositoryMock->shouldReceive('getLocalRepository')
            ->andReturn($localRepositoryMock);

        $this->composerMock->shouldReceive('getRepositoryManager')
            ->andReturn($repositoryMock);

        $installationManager = $this->mock(InstallationManager::class);
        $installationManager->shouldReceive('addInstaller')
            ->once()
            ->with(\Mockery::type(ConfiguratorInstaller::class));

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManager);

        $downloaderMock = $this->mock(DownloaderInterface::class);

        $downloadManagerMock = $this->mock(DownloadManager::class);
        $downloadManagerMock->shouldReceive('getDownloader')
            ->once()
            ->with('file')
            ->andReturn($downloaderMock);

        $this->composerMock->shouldReceive('getDownloadManager')
            ->twice()
            ->andReturn($downloadManagerMock);

        $pluginManagerMock = $this->mock(PluginManager::class);
        $pluginManagerMock->shouldReceive('getPlugins')
            ->once()
            ->andReturn([]);

        $this->composerMock->shouldReceive('getPluginManager')
            ->once()
            ->andReturn($pluginManagerMock);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Composer >=1.7 not found, downloads will happen in sequence', true, IOInterface::DEBUG);

        $inputMock = $this->mock(InputInterface::class);
        $inputMock->shouldReceive('getFirstArgument')
            ->once()
            ->andReturn(null);

        $input = &$this->getGenericPropertyReader()($this->ioMock, 'input');
        $input = $inputMock;

        $this->discovery->activate($this->composerMock, $this->ioMock);

        self::assertInstanceOf(Lock::class, $this->discovery->getLock());
        self::assertInstanceOf(Configurator::class, $this->discovery->getConfigurator());

        self::assertSame(
            [
                'This file locks the discovery information of your project to a known state',
                'This file is @generated automatically',
            ],
            $this->discovery->getLock()->get('@readme')
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    private function arrangeDiscoveryConfig(): void
    {
        $this->configMock->shouldReceive('get')
            ->twice()
            ->with('vendor-dir')
            ->andReturn(__DIR__);
        $this->configMock->shouldReceive('get')
            ->once()
            ->with('bin-dir')
            ->andReturn(__DIR__);
        $this->configMock->shouldReceive('get')
            ->once()
            ->with('bin-compat')
            ->andReturn(__DIR__);
        $this->configMock->shouldReceive('get')
            ->once()
            ->with('disable-tls')
            ->andReturn(null);
        $this->configMock->shouldReceive('get')
            ->once()
            ->with('cafile')
            ->andReturn(null);
        $this->configMock->shouldReceive('get')
            ->once()
            ->with('capath')
            ->andReturn(null);
        $this->configMock->shouldReceive('get')
            ->once()
            ->with('cache-files-dir')
            ->andReturn(__DIR__);
        $this->composerMock->shouldReceive('getConfig')
            ->andReturn($this->configMock);
    }
}
