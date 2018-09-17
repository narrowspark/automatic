<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\Composer;
use Composer\Config;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Downloader\DownloadManager;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallationManager;
use Composer\Installer\InstallerEvent;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents as ComposerScriptEvents;
use Composer\Util\ProcessExecutor;
use Composer\Util\RemoteFilesystem;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Installer\ConfiguratorInstaller;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\Lock;
use Narrowspark\Automatic\Prefetcher\ParallelDownloader;
use Narrowspark\Automatic\Prefetcher\Prefetcher;
use Narrowspark\Automatic\ScriptEvents;
use Narrowspark\Automatic\ScriptExecutor;
use Narrowspark\Automatic\ScriptExtender\ScriptExtender;
use Narrowspark\Automatic\Test\Traits\ArrangeComposerClasses;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Nyholm\NSA;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final class AutomaticTest extends MockeryTestCase
{
    use ArrangeComposerClasses;

    /**
     * @var \Narrowspark\Automatic\Automatic
     */
    private $automatic;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->composerCachePath = __DIR__ . '/AutomaticTest';

        \mkdir($this->composerCachePath);
        \putenv('COMPOSER_CACHE_DIR=' . $this->composerCachePath);

        $this->arrangeComposerClasses();

        $this->automatic = new class() extends Automatic {
            public function setContainer($container): void
            {
                $this->container = $container;
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        \putenv('COMPOSER_CACHE_DIR=');
        \putenv('COMPOSER_CACHE_DIR');

        (new Filesystem())->remove([$this->composerCachePath, __DIR__ . \DIRECTORY_SEPARATOR . 'narrowspark']);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertCount(14, Automatic::getSubscribedEvents());

        NSA::setProperty($this->automatic, 'activated', false);

        static::assertCount(0, Automatic::getSubscribedEvents());
    }

    public function testActivate(): void
    {
        $this->arrangeAutomaticConfig();
        $this->arrangePackagist();

        $this->composerMock->shouldReceive('getPackage->getExtra')
            ->once()
            ->andReturn([]);
        $this->composerMock->shouldReceive('getPackage->getMinimumStability')
            ->once()
            ->andReturn(null);

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
        $installationManager->shouldReceive('addInstaller')
            ->once()
            ->with(\Mockery::type(SkeletonInstaller::class));

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManager);

        $downloadManagerMock = $this->mock(DownloadManager::class);

        $this->composerMock->shouldReceive('getDownloadManager')
            ->twice()
            ->andReturn($downloadManagerMock);

        $this->composerMock->shouldReceive('getEventDispatcher')
            ->once()
            ->andReturn($this->mock(EventDispatcher::class));

        $this->composerMock->shouldReceive('setRepositoryManager')
            ->with(\Mockery::type(RepositoryManager::class))
            ->once();

        if (! \method_exists(RemoteFilesystem::class, 'getRemoteContents')) {
            $this->ioMock->shouldReceive('writeError')
                ->once()
                ->with('Composer >=1.7 not found, downloads will happen in sequence', true, IOInterface::DEBUG);
        }

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(true);
        $this->ioMock->input = new ArrayInput([]);
        $this->ioMock->shouldReceive('writeError')
            ->atLeast()
            ->once();

        $this->automatic->activate($this->composerMock, $this->ioMock);

        static::assertSame(
            [
                'This file locks the automatic information of your project to a known state',
                'This file is @generated automatically',
            ],
            $this->automatic->getContainer()->get(Lock::class)->get('@readme')
        );

        static::assertInstanceOf(\Narrowspark\Automatic\Installer\InstallationManager::class, $this->automatic->getContainer()->get(\Narrowspark\Automatic\Installer\InstallationManager::class));
    }

    public function testActivateWithNoInteractive(): void
    {
        $this->ioMock->shouldReceive('isInteractive')
            ->andReturn(false);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('<warning>Narrowspark Automatic has been disabled. Composer running in a no interaction mode</warning>');

        $this->automatic->activate($this->composerMock, $this->ioMock);
    }

    public function testRecordWithUpdateRecord(): void
    {
        $packageEventMock = $this->mock(PackageEvent::class);

        $packageMock = $this->mock(Package::class);
        $packageMock->shouldReceive('getName')
            ->andReturn('test');
        $packageMock->shouldReceive('getType')
            ->andReturn('library');

        $updateOperationMock = $this->mock(UpdateOperation::class);
        $updateOperationMock->shouldReceive('getTargetPackage')
            ->andReturn($packageMock);

        $packageEventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($updateOperationMock);
        $packageEventMock->shouldReceive('isDevMode')
            ->andReturn(false);

        $this->automatic->record($packageEventMock);
    }

    public function testRecordWithInstallRecord(): void
    {
        \putenv('COMPOSER_VENDOR_DIR=' . __DIR__);

        $automatic = new Automatic();

        $packageEventMock = $this->mock(PackageEvent::class);

        $packageMock = $this->mock(Package::class);
        $packageMock->shouldReceive('getName')
            ->andReturn('test');
        $packageMock->shouldReceive('getType')
            ->andReturn('library');

        $installerOperationMock = $this->mock(InstallOperation::class);
        $installerOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        $packageEventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($installerOperationMock);
        $packageEventMock->shouldReceive('isDevMode')
            ->andReturn(false);

        $repositoryMock = $this->arrangeLocalRepository();

        $rootPackageMock = $this->mock(RootPackage::class);
        $rootPackageMock->shouldReceive('getExtra')
            ->once()
            ->andReturn([]);

        $composer = new Composer();
        $composer->setInstallationManager(new InstallationManager());
        $composer->setConfig(new Config());
        $composer->setRepositoryManager($repositoryMock);
        $composer->setPackage($rootPackageMock);

        $automatic->activate(
            $composer,
            new class() extends NullIO {
                /**
                 * {@inheritdoc}
                 */
                public function isInteractive(): bool
                {
                    return true;
                }
            }
        );

        $automatic->record($packageEventMock);

        \putenv('COMPOSER_VENDOR_DIR=');
        \putenv('COMPOSER_VENDOR_DIR');
    }

    public function testRecordWithInstallRecordAndAutomaticPackage(): void
    {
        \putenv('COMPOSER_VENDOR_DIR=' . __DIR__);

        $automatic = new Automatic();

        $packageEventMock = $this->mock(PackageEvent::class);

        $packageMock = $this->mock(Package::class);
        $packageMock->shouldReceive('getName')
            ->andReturn('test');
        $packageMock->shouldReceive('getType')
            ->andReturn('library');

        $installerOperationMock = $this->mock(InstallOperation::class);
        $installerOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        $packageEventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($installerOperationMock);
        $packageEventMock->shouldReceive('isDevMode')
            ->andReturn(false);

        $automaticPackageEventMock = $this->mock(PackageEvent::class);

        $automaticPackageMock = $this->mock(Package::class);
        $automaticPackageMock->shouldReceive('getName')
            ->twice()
            ->andReturn(Automatic::PACKAGE_NAME);
        $automaticPackageMock->shouldReceive('getType')
            ->andReturn('composer-plugin');

        $automaticInstallerOperationMock = $this->mock(InstallOperation::class);
        $automaticInstallerOperationMock->shouldReceive('getPackage')
            ->andReturn($automaticPackageMock);

        $automaticPackageEventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($automaticInstallerOperationMock);
        $automaticPackageEventMock->shouldReceive('isDevMode')
            ->andReturn(false);

        $repositoryMock = $this->arrangeLocalRepository();

        $rootPackageMock = $this->mock(RootPackage::class);
        $rootPackageMock->shouldReceive('getExtra')
            ->once()
            ->andReturn([]);

        $composer = new Composer();
        $composer->setInstallationManager(new InstallationManager());
        $composer->setConfig(new Config());
        $composer->setRepositoryManager($repositoryMock);
        $composer->setPackage($rootPackageMock);

        $automatic->activate(
            $composer,
            new class() extends NullIO {
                /**
                 * {@inheritdoc}
                 */
                public function isInteractive(): bool
                {
                    return true;
                }
            }
        );

        $automatic->record($packageEventMock);
        $automatic->record($automaticPackageEventMock);

        \putenv('COMPOSER_VENDOR_DIR=');
        \putenv('COMPOSER_VENDOR_DIR');
    }

    public function testExecuteAutoScripts(): void
    {
        \putenv('COMPOSER=' . __DIR__ . '/Fixture/composer.json');

        $eventMock = $this->mock(Event::class);
        $eventMock->shouldReceive('stopPropagation')
            ->once();

        $processExecutorMock = $this->mock(ProcessExecutor::class);
        $processExecutorMock->shouldReceive('execute')
            ->andReturn(0);

        $scriptExecutor = new ScriptExecutor(new Composer(), new NullIO(), $processExecutorMock, []);

        $lockMock = $this->mock(Lock::class);
        $lockMock->shouldReceive('get')
            ->once()
            ->with(ScriptExecutor::TYPE)
            ->andReturn(['test/test' => [ScriptExtender::class => \dirname(__DIR__, 2) . '/Automatic/ScriptExtender.php']]);

        $containerMock = $this->mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->once()
            ->with(ScriptExecutor::class)
            ->andReturn($scriptExecutor);
        $containerMock->shouldReceive('get')
            ->once()
            ->with(Lock::class)
            ->andReturn($lockMock);

        $this->automatic->setContainer($containerMock);
        $this->automatic->executeAutoScripts($eventMock);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    public function testExecuteAutoScriptsWithoutScripts(): void
    {
        $eventMock = $this->mock(Event::class);
        $eventMock->shouldReceive('stopPropagation')
            ->once();

        $this->ioMock->shouldReceive('write')
            ->once()
            ->with('No auto-scripts section was found under scripts', true, IOInterface::VERBOSE);

        $containerMock = $this->mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->once()
            ->with(IOInterface::class)
            ->andReturn($this->ioMock);

        $this->automatic->setContainer($containerMock);
        $this->automatic->executeAutoScripts($eventMock);
    }

    public function testPostMessages(): void
    {
        $eventMock = $this->mock(Event::class);
        $eventMock->shouldReceive('stopPropagation')
            ->once();

        $this->ioMock->shouldReceive('write')
            ->once()
            ->with(['']);

        $containerMock = $this->mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->once()
            ->with(IOInterface::class)
            ->andReturn($this->ioMock);

        $this->automatic->setContainer($containerMock);
        $this->automatic->postMessages($eventMock);
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

        $this->automatic->setContainer($containerMock);
        $this->automatic->populateFilesCacheDir($event);
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

        $this->automatic->setContainer($containerMock);
        $this->automatic->onFileDownload($event);
    }

    public function testGetAutomaticLockFile(): void
    {
        static::assertSame('./automatic.lock', Automatic::getAutomaticLockFile());
    }

    public function testOnPostUninstall(): void
    {
        $event = $this->mock(PackageEvent::class);
        $event->shouldReceive('getOperation->getPackage->getName')
            ->once()
            ->andReturn(Automatic::PACKAGE_NAME);
        $event->shouldReceive('isDevMode')
            ->once()
            ->andReturn(true);

        $filePath     = __DIR__ . \DIRECTORY_SEPARATOR . 'composer_uninstall.json';
        $lockfilePath = \mb_substr($filePath, 0, -4) . 'lock';

        $scripts  = [
            ScriptEvents::POST_MESSAGES            => '',
            ComposerScriptEvents::POST_INSTALL_CMD => [
                '@' . ScriptEvents::POST_MESSAGES,
                '@' . ScriptEvents::AUTO_SCRIPTS,
            ],
            ComposerScriptEvents::POST_UPDATE_CMD => [
                '@' . ScriptEvents::POST_MESSAGES,
                '@' . ScriptEvents::AUTO_SCRIPTS,
            ],
            'test' => 'this should stay',
        ];

        \file_put_contents($filePath, \json_encode([
            'scripts' => $scripts,
        ]));
        \file_put_contents($lockfilePath, \json_encode([]));

        \putenv('COMPOSER=' . $filePath);

        $this->composerMock->shouldReceive('getPackage->getScripts')
            ->once()
            ->andReturn($scripts);

        $repositoryMock = $this->arrangeLocalRepository();

        $this->composerMock->shouldReceive('getRepositoryManager')
            ->andReturn($repositoryMock);

        $installationManager = $this->mock(InstallationManager::class);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManager);

        $containerMock = $this->mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->twice()
            ->with(Composer::class)
            ->andReturn($this->composerMock);

        $this->ioMock->shouldReceive('isDebug')
            ->once()
            ->andReturn(false);

        $containerMock->shouldReceive('get')
            ->twice()
            ->with(IOInterface::class)
            ->andReturn($this->ioMock);

        $this->automatic->setContainer($containerMock);
        $this->automatic->onPostUninstall($event);

        $jsonData = \json_decode(\file_get_contents($filePath), true);

        static::assertArrayNotHasKey(ScriptEvents::POST_MESSAGES, $jsonData['scripts']);
        static::assertArrayHasKey('test', $jsonData['scripts']);
        static::assertCount(0, $jsonData['scripts'][ComposerScriptEvents::POST_INSTALL_CMD]);
        static::assertCount(0, $jsonData['scripts'][ComposerScriptEvents::POST_INSTALL_CMD]);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');

        @\unlink($filePath);
        @\unlink($lockfilePath);
    }

    public function testOnPostUninstallWithWithoutDev(): void
    {
        $event = $this->mock(PackageEvent::class);

        $event->shouldReceive('isDevMode')
            ->once()
            ->andReturn(false);
        $event->shouldReceive('getComposer->getLocker->getLockData')
            ->once()
            ->andReturn(['packages-dev' => [['name' => Automatic::PACKAGE_NAME]]]);
        $event->shouldReceive('getOperation->getPackage->getName')
            ->never();

        $this->automatic->onPostUninstall($event);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    private function arrangeAutomaticConfig(): void
    {
        $this->configMock->shouldReceive('get')
            ->times(3)
            ->with('vendor-dir')
            ->andReturn(__DIR__);
        $this->configMock->shouldReceive('get')
            ->twice()
            ->with('bin-dir')
            ->andReturn(__DIR__);
        $this->configMock->shouldReceive('get')
            ->twice()
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
            ->with('cache-repo-dir')
            ->andReturn('repo');

        $this->composerMock->shouldReceive('getConfig')
            ->andReturn($this->configMock);
    }

    /**
     * @return \Mockery\MockInterface
     */
    private function arrangeLocalRepository(): \Mockery\MockInterface
    {
        $localRepositoryMock = $this->mock(WritableRepositoryInterface::class);

        $repositoryMock = $this->mock(RepositoryManager::class);
        $repositoryMock->shouldReceive('getLocalRepository')
            ->andReturn($localRepositoryMock);

        return $repositoryMock;
    }
}
