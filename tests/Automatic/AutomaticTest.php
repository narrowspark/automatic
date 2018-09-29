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
use Composer\Package\PackageInterface;
use Composer\Package\RootPackage;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents as ComposerScriptEvents;
use Composer\Util\ProcessExecutor;
use Composer\Util\RemoteFilesystem;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Installer\ConfiguratorInstaller;
use Narrowspark\Automatic\Installer\InstallationManager as NarrowsparkInstallationManager;
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
        static::assertCount(15, Automatic::getSubscribedEvents());

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

        static::assertInstanceOf(
            NarrowsparkInstallationManager::class,
            $this->automatic->getContainer()->get(NarrowsparkInstallationManager::class)
        );
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

    public function testRecordWithInstallRecordAndLock(): void
    {
        \putenv('COMPOSER_VENDOR_DIR=' . __DIR__);

        $packageEventMock = $this->mock(PackageEvent::class);

        $name = 'test/foo';

        $packageMock = $this->mock(Package::class);
        $packageMock->shouldReceive('getName')
            ->andReturn($name);
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

        $this->lockMock->shouldReceive('has')
            ->with($name)
            ->andReturn(true);

        $containerMock = $this->mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->with(Lock::class)
            ->andReturn($this->lockMock);

        $this->automatic->setContainer($containerMock);
        $this->automatic->record($packageEventMock);

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
            ComposerScriptEvents::POST_INSTALL_CMD => [
                '@' . ScriptEvents::AUTO_SCRIPTS,
            ],
            ComposerScriptEvents::POST_UPDATE_CMD => [
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

    public function testOnPostUpdatePostMessages(): void
    {
        $this->ioMock->shouldReceive('write')
            ->once();

        $container = $this->mock(ContainerContract::class);
        $container->shouldReceive('get')
            ->once()
            ->with(IOInterface::class)
            ->andReturn($this->ioMock);

        $this->automatic->setContainer($container);

        $this->automatic->onPostUpdatePostMessages($this->mock(Event::class));
    }

    public function testInitAutoScripts(): void
    {
        $composerJsonPath = __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'testInitAutoScripts.json';
        $composerLockPath = \mb_substr($composerJsonPath, 0, -4) . 'lock';

        \file_put_contents($composerJsonPath, \json_encode(['test' => []]));
        \file_put_contents($composerLockPath, \json_encode(['packages' => []]));

        \putenv('COMPOSER=' . $composerJsonPath);

        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getScripts')
            ->once()
            ->andReturn([]);

        $this->composerMock
            ->shouldReceive('getPackage')
            ->once()
            ->andReturn($packageMock);

        $this->automatic->setContainer($this->arrangeUpdateComposerLock());

        $this->automatic->initAutoScripts();

        $jsonContent = \json_decode(\file_get_contents($composerJsonPath), true);

        static::assertTrue(isset($jsonContent['scripts']));
        static::assertTrue(isset($jsonContent['scripts']['post-install-cmd']));
        static::assertTrue(isset($jsonContent['scripts']['post-update-cmd']));
        static::assertSame('@' . ScriptEvents::AUTO_SCRIPTS, $jsonContent['scripts']['post-install-cmd'][0]);
        static::assertSame('@' . ScriptEvents::AUTO_SCRIPTS, $jsonContent['scripts']['post-update-cmd'][0]);

        $lockContent = \json_decode(\file_get_contents($composerLockPath), true);

        static::assertInternalType('string', $lockContent['content-hash']);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');

        @\unlink($composerJsonPath);
        @\unlink($composerLockPath);
    }

    public function testInitAutoScriptsWithAutoScriptInComposerJson(): void
    {
        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getScripts')
            ->once()
            ->andReturn([
                ComposerScriptEvents::POST_UPDATE_CMD => [
                    '@' . ScriptEvents::AUTO_SCRIPTS,
                ],
                ComposerScriptEvents::POST_INSTALL_CMD => [
                    '@' . ScriptEvents::AUTO_SCRIPTS,
                ],
            ]);
        $this->composerMock
            ->shouldReceive('getPackage')
            ->once()
            ->andReturn($packageMock);

        $containerMock = $this->mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->with(Composer::class)
            ->andReturn($this->composerMock);

        $this->automatic->setContainer($containerMock);
        $this->automatic->initAutoScripts();
    }

    public function testOnPostCreateProject(): void
    {
        $composerJsonPath = __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'testOnPostCreateProject.json';
        $composerLockPath = \mb_substr($composerJsonPath, 0, -4) . 'lock';

        \file_put_contents($composerJsonPath, \json_encode([
            'name'        => 'narrowspark/narrowspark',
            'type'        => 'project',
            'description' => 'A skeleton to start a new Narrowspark project.',
            'license'     => 'MIT',
        ]));
        \file_put_contents($composerLockPath, \json_encode(['packages' => []]));

        \putenv('COMPOSER=' . $composerJsonPath);

        $containerMock = $this->arrangeUpdateComposerLock();
        $containerMock->shouldReceive('get')
            ->with('composer-extra')
            ->andReturn([
                'test' => 'foo',
            ]);
        $this->automatic->setContainer($containerMock);

        $this->automatic->onPostCreateProject($this->mock(Event::class));

        $jsonContent = \json_decode(\file_get_contents($composerJsonPath), true);

        static::assertFalse(isset($jsonContent['name']));
        static::assertFalse(isset($jsonContent['description']));
        static::assertFalse(isset($jsonContent['description']));
        static::assertSame('proprietary', $jsonContent['license']);
        static::assertTrue(isset($jsonContent['extra']));
        static::assertTrue(isset($jsonContent['extra']['test']));
        static::assertSame('foo', $jsonContent['extra']['test']);

        $lockContent = \json_decode(\file_get_contents($composerLockPath), true);

        static::assertInternalType('string', $lockContent['content-hash']);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');

        @\unlink($composerJsonPath);
        @\unlink($composerLockPath);
    }

    public function testRunSkeletonGeneratorWithoutInstaller(): void
    {
        $this->lockMock->shouldReceive('read')
            ->once();
        $this->lockMock->shouldReceive('has')
            ->once()
            ->with(SkeletonInstaller::LOCK_KEY)
            ->andReturn(false);
        $this->lockMock->shouldReceive('reset')
            ->once();

        $containerMock = $this->mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->once()
            ->with(Lock::class)
            ->andReturn($this->lockMock);
        $this->automatic->setContainer($containerMock);

        $this->automatic->runSkeletonGenerator($this->mock(Event::class));
    }

    public function testOnPostAutoloadDump(): void
    {
        $containerMock    = $this->mock(ContainerContract::class);
        $configuratorMock = $this->mock(ConfiguratorContract::class);
        $configuratorMock->shouldReceive('reset')
            ->never();
        $configuratorMock->shouldReceive('add')
            ->once()
            ->with('test', 'Test\Configurator');

        $containerMock->shouldReceive('get')
            ->once()
            ->with(ConfiguratorContract::class)
            ->andReturn($configuratorMock);

        $this->lockMock->shouldReceive('get')
            ->once()
            ->with(Automatic::LOCK_CLASSMAP)
            ->andReturn([
                'prisis/install' => [
                    '\Test\Configurator' => '%vendor_path%/prisis/install/Configurator.php',
                ],
            ]);
        $this->lockMock->shouldReceive('get')
            ->with(ConfiguratorInstaller::LOCK_KEY)
            ->andReturn([
                'prisis/install' => [
                    '\Test\Configurator',
                ],
            ]);

        $containerMock->shouldReceive('get')
            ->once()
            ->with(Lock::class)
            ->andReturn($this->lockMock);
        $containerMock->shouldReceive('get')
            ->once()
            ->with('vendor-dir')
            ->andReturn(__DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'Configurator');

        $this->automatic->setContainer($containerMock);
        $this->automatic->onPostAutoloadDump($this->mock(Event::class));

        NSA::setProperty($this->automatic, 'configuratorsLoaded', false);
    }

    public function testOnPostAutoloadDumpWithReset(): void
    {
        $containerMock    = $this->mock(ContainerContract::class);
        $configuratorMock = $this->mock(ConfiguratorContract::class);
        $configuratorMock->shouldReceive('reset')
            ->once();
        $configuratorMock->shouldReceive('add')
            ->twice()
            ->with('test', 'Test\Configurator');

        $containerMock->shouldReceive('get')
            ->twice()
            ->with(ConfiguratorContract::class)
            ->andReturn($configuratorMock);

        $this->lockMock->shouldReceive('get')
            ->twice()
            ->with(Automatic::LOCK_CLASSMAP)
            ->andReturn([
                'prisis/install' => [
                    '\Test\Configurator' => '%vendor_path%/prisis/install/Configurator.php',
                ],
            ]);
        $this->lockMock->shouldReceive('get')
            ->twice()
            ->with(ConfiguratorInstaller::LOCK_KEY)
            ->andReturn([
                'prisis/install' => [
                    '\Test\Configurator',
                ],
            ]);

        $containerMock->shouldReceive('get')
            ->twice()
            ->with(Lock::class)
            ->andReturn($this->lockMock);
        $containerMock->shouldReceive('get')
            ->twice()
            ->with('vendor-dir')
            ->andReturn(__DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'Configurator');

        $event = $this->mock(Event::class);

        $this->automatic->setContainer($containerMock);
        $this->automatic->onPostAutoloadDump($event);
        $this->automatic->onPostAutoloadDump($event);
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

    /**
     * @return \Mockery\MockInterface|\Narrowspark\Automatic\Contract\Container
     */
    private function arrangeUpdateComposerLock()
    {
        $repositoryManagerMock = $this->mock(RepositoryManager::class);

        $this->composerMock->shouldReceive('getRepositoryManager')
            ->once()
            ->andReturn($repositoryManagerMock);

        $installationManagerMock = $this->mock(InstallationManager::class);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManagerMock);

        $containerMock = $this->mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->with(Composer::class)
            ->andReturn($this->composerMock);
        $containerMock->shouldReceive('get')
            ->with(IOInterface::class)
            ->andReturn(new NullIO());

        return $containerMock;
    }
}
