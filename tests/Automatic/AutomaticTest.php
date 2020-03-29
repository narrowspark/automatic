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

namespace Narrowspark\Automatic\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Downloader\DownloaderInterface;
use Composer\Downloader\DownloadManager;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents as ComposerScriptEvents;
use Composer\Util\ProcessExecutor;
use Composer\Util\RemoteFilesystem;
use Mockery;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\FunctionMock;
use Narrowspark\Automatic\Installer\ConfiguratorInstaller;
use Narrowspark\Automatic\Installer\InstallationManager as NarrowsparkInstallationManager;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\Lock;
use Narrowspark\Automatic\ScriptEvents;
use Narrowspark\Automatic\ScriptExecutor;
use Narrowspark\Automatic\ScriptExtender\ScriptExtender;
use Narrowspark\Automatic\Tests\Traits\ArrangeComposerClassesTrait;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Nyholm\NSA;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Automatic
 *
 * @medium
 */
final class AutomaticTest extends MockeryTestCase
{
    use ArrangeComposerClassesTrait;
    use GetGenericPropertyReaderTrait;

    /** @var \Narrowspark\Automatic\Automatic */
    private $plugin;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composerCachePath = __DIR__ . '/AutomaticTest';

        @\mkdir($this->composerCachePath);
        \putenv('COMPOSER_CACHE_DIR=' . $this->composerCachePath);

        $this->arrangeComposerClasses();

        $this->plugin = new class() extends Automatic {
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

        FunctionMock::$isOpensslActive = true;

        \putenv('COMPOSER_CACHE_DIR=');
        \putenv('COMPOSER_CACHE_DIR');

        $narrowsparkPath = __DIR__ . \DIRECTORY_SEPARATOR . 'narrowspark';

        $this->delete($this->composerCachePath);
        $this->delete($narrowsparkPath);

        @\unlink($this->composerCachePath . \DIRECTORY_SEPARATOR . '.htaccess');
        @\rmdir($this->composerCachePath);
        @\rmdir($narrowsparkPath);
    }

    public function testGetSubscribedEvents(): void
    {
        NSA::setProperty($this->plugin, 'activated', true);

        self::assertCount(10, Automatic::getSubscribedEvents());

        NSA::setProperty($this->plugin, 'activated', false);

        self::assertCount(0, Automatic::getSubscribedEvents());
    }

    /**
     * @group network
     */
    public function testActivate(): void
    {
        $this->configMock->shouldReceive('get')
            ->with('cache-files-dir')
            ->andReturn('');

        $this->arrangeAutomaticConfig();
        $this->arrangePackagist();

        $this->composerMock->shouldReceive('getPackage->getMinimumStability')
            ->once()
            ->andReturn(null);

        $localRepositoryMock = Mockery::mock(WritableRepositoryInterface::class);

        $repositoryMock = Mockery::mock(RepositoryManager::class);
        $repositoryMock->shouldReceive('getLocalRepository')
            ->andReturn($localRepositoryMock);

        $this->composerMock->shouldReceive('getRepositoryManager')
            ->andReturn($repositoryMock);

        $installationManager = Mockery::mock(InstallationManager::class);
        $installationManager->shouldReceive('addInstaller')
            ->once()
            ->with(Mockery::type(ConfiguratorInstaller::class));
        $installationManager->shouldReceive('addInstaller')
            ->once()
            ->with(Mockery::type(SkeletonInstaller::class));

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManager);

        $downloadManagerMock = Mockery::mock(DownloadManager::class);
        $downloadManagerMock->shouldReceive('getDownloader')
            ->with('file')
            ->andReturn(Mockery::mock(DownloaderInterface::class));

        $this->composerMock->shouldReceive('getDownloadManager')
            ->times(2)
            ->andReturn($downloadManagerMock);

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
        $this->ioMock->shouldReceive('loadConfiguration');

        $this->plugin->activate($this->composerMock, $this->ioMock);
        $genericPropertyReader = $this->getGenericPropertyReader();

        $container = $genericPropertyReader($this->plugin, 'container');

        self::assertSame(
            [
                'This file locks the automatic information of your project to a known state',
                'This file is @generated automatically',
            ],
            $container->get(Lock::class)->get('@readme')
        );

        self::assertInstanceOf(
            NarrowsparkInstallationManager::class,
            $container->get(NarrowsparkInstallationManager::class)
        );
    }

    public function testActivateWithNoInteractive(): void
    {
        $this->ioMock->shouldReceive('isInteractive')
            ->andReturn(false);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('<warning>Narrowspark Automatic has been disabled. Composer running in a no interaction mode</warning>');

        $this->plugin->activate($this->composerMock, $this->ioMock);
    }

    public function testActivateWithNoOpenssl(): void
    {
        FunctionMock::$isOpensslActive = false;

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('<warning>Narrowspark Automatic has been disabled. You must enable the openssl extension in your [php.ini] file</warning>');

        $this->plugin->activate($this->composerMock, $this->ioMock);
    }

    public function testRecordWithUpdateRecord(): void
    {
        $packageEventMock = Mockery::mock(PackageEvent::class);

        $packageMock = Mockery::mock(Package::class);
        $packageMock->shouldReceive('getName')
            ->andReturn('test');
        $packageMock->shouldReceive('getType')
            ->andReturn('library');

        $updateOperationMock = Mockery::mock(UpdateOperation::class);
        $updateOperationMock->shouldReceive('getTargetPackage')
            ->andReturn($packageMock);

        $packageEventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($updateOperationMock);
        $packageEventMock->shouldReceive('isDevMode')
            ->andReturn(false);

        $this->plugin->record($packageEventMock);
    }

    public function testRecordWithInstallRecord(): void
    {
        \putenv('COMPOSER_VENDOR_DIR=' . __DIR__);

        $automatic = new Automatic();

        $packageEventMock = Mockery::mock(PackageEvent::class);

        $packageMock = Mockery::mock(Package::class);
        $packageMock->shouldReceive('getName')
            ->andReturn('test');
        $packageMock->shouldReceive('getType')
            ->andReturn('library');

        $installerOperationMock = Mockery::mock(InstallOperation::class);
        $installerOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        $packageEventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($installerOperationMock);
        $packageEventMock->shouldReceive('isDevMode')
            ->andReturn(false);

        $repositoryMock = $this->arrangeLocalRepository();

        $composer = new Composer();
        $composer->setInstallationManager(new InstallationManager());
        $composer->setConfig(new Config());
        $composer->setRepositoryManager($repositoryMock);

        $downloadManagerMock = Mockery::mock(DownloadManager::class);
        $downloadManagerMock->shouldReceive('getDownloader')
            ->with('file')
            ->andReturn(Mockery::mock(DownloaderInterface::class));

        $composer->setDownloadManager($downloadManagerMock);

        $automatic->activate(
            $composer,
            new class() extends NullIO {
                protected $input;

                public function __construct()
                {
                    $this->input = new ArrayInput([]);
                }

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

        $packageEventMock = Mockery::mock(PackageEvent::class);

        $name = 'test/foo';

        $packageMock = Mockery::mock(Package::class);
        $packageMock->shouldReceive('getName')
            ->andReturn($name);
        $packageMock->shouldReceive('getType')
            ->andReturn('library');

        $installerOperationMock = Mockery::mock(InstallOperation::class);
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

        $containerMock = Mockery::mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->with(Lock::class)
            ->andReturn($this->lockMock);

        $this->plugin->setContainer($containerMock);
        $this->plugin->record($packageEventMock);

        \putenv('COMPOSER_VENDOR_DIR=');
        \putenv('COMPOSER_VENDOR_DIR');
    }

    public function testRecordWithInstallRecordAndAutomaticPackage(): void
    {
        \putenv('COMPOSER_VENDOR_DIR=' . __DIR__);

        $automatic = new Automatic();

        $packageEventMock = Mockery::mock(PackageEvent::class);

        $packageMock = Mockery::mock(Package::class);
        $packageMock->shouldReceive('getName')
            ->andReturn('test');
        $packageMock->shouldReceive('getType')
            ->andReturn('library');

        $installerOperationMock = Mockery::mock(InstallOperation::class);
        $installerOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        $packageEventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($installerOperationMock);
        $packageEventMock->shouldReceive('isDevMode')
            ->andReturn(false);

        $automaticPackageEventMock = Mockery::mock(PackageEvent::class);

        $automaticPackageMock = Mockery::mock(Package::class);
        $automaticPackageMock->shouldReceive('getName')
            ->twice()
            ->andReturn(Automatic::PACKAGE_NAME);
        $automaticPackageMock->shouldReceive('getType')
            ->andReturn('composer-plugin');

        $automaticInstallerOperationMock = Mockery::mock(InstallOperation::class);
        $automaticInstallerOperationMock->shouldReceive('getPackage')
            ->andReturn($automaticPackageMock);

        $automaticPackageEventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($automaticInstallerOperationMock);
        $automaticPackageEventMock->shouldReceive('isDevMode')
            ->andReturn(false);

        $repositoryMock = $this->arrangeLocalRepository();

        $composer = new Composer();
        $composer->setInstallationManager(new InstallationManager());
        $composer->setConfig(new Config());
        $composer->setRepositoryManager($repositoryMock);

        $downloadManagerMock = Mockery::mock(DownloadManager::class);
        $downloadManagerMock->shouldReceive('getDownloader')
            ->with('file')
            ->andReturn(Mockery::mock(DownloaderInterface::class));

        $composer->setDownloadManager($downloadManagerMock);

        $automatic->activate(
            $composer,
            new class() extends NullIO {
                protected $input;

                public function __construct()
                {
                    $this->input = new ArrayInput([]);
                }

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

        $eventMock = Mockery::mock(Event::class);
        $eventMock->shouldReceive('stopPropagation')
            ->once();

        $processExecutorMock = Mockery::mock(ProcessExecutor::class);
        $processExecutorMock->shouldReceive('execute')
            ->andReturn(0);

        $scriptExecutor = new ScriptExecutor(new Composer(), new NullIO(), $processExecutorMock, []);

        $lockMock = Mockery::mock(Lock::class);
        $lockMock->shouldReceive('get')
            ->once()
            ->with(ScriptExecutor::TYPE)
            ->andReturn(['test/test' => [ScriptExtender::class => \dirname(__DIR__, 2) . '/Automatic/ScriptExtender.php']]);

        $containerMock = Mockery::mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->once()
            ->with(ScriptExecutor::class)
            ->andReturn($scriptExecutor);
        $containerMock->shouldReceive('get')
            ->once()
            ->with(Lock::class)
            ->andReturn($lockMock);

        $this->plugin->setContainer($containerMock);
        $this->plugin->executeAutoScripts($eventMock);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    public function testExecuteAutoScriptsWithNumericArray(): void
    {
        \putenv('COMPOSER=' . __DIR__ . '/Fixture/composer-with-numeric-scripts.json');

        $eventMock = Mockery::mock(Event::class);
        $eventMock->shouldReceive('stopPropagation')
            ->never();

        $containerMock = Mockery::mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->never()
            ->with(ScriptExecutor::class);
        $containerMock->shouldReceive('get')
            ->never()
            ->with(Lock::class);

        $this->plugin->setContainer($containerMock);
        $this->plugin->executeAutoScripts($eventMock);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    public function testExecuteAutoScriptsWithoutScripts(): void
    {
        \putenv('COMPOSER=' . __DIR__ . '/Fixture/composer-empty-scripts.json');

        $eventMock = Mockery::mock(Event::class);
        $eventMock->shouldReceive('stopPropagation')
            ->never();

        $this->ioMock->shouldReceive('write')
            ->once()
            ->with('No auto-scripts section was found under scripts', true, IOInterface::VERBOSE);

        $containerMock = Mockery::mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->once()
            ->with(IOInterface::class)
            ->andReturn($this->ioMock);

        $this->plugin->setContainer($containerMock);
        $this->plugin->executeAutoScripts($eventMock);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    public function testGetAutomaticLockFile(): void
    {
        self::assertSame('./automatic.lock', Automatic::getAutomaticLockFile());
    }

    public function testOnPostUninstall(): void
    {
        $event = Mockery::mock(PackageEvent::class);
        $event->shouldReceive('getOperation->getPackage->getName')
            ->once()
            ->andReturn(Automatic::PACKAGE_NAME);
        $event->shouldReceive('isDevMode')
            ->once()
            ->andReturn(true);

        $filePath = __DIR__ . \DIRECTORY_SEPARATOR . 'composer_uninstall.json';
        $lockfilePath = \mb_substr($filePath, 0, -4) . 'lock';

        $scripts = [
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

        $installationManager = Mockery::mock(InstallationManager::class);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManager);

        $containerMock = Mockery::mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->twice()
            ->with(Composer::class)
            ->andReturn($this->composerMock);
        $containerMock->shouldReceive('get')
            ->with(Filesystem::class)
            ->andReturn(new Filesystem());

        $this->ioMock->shouldReceive('isDebug')
            ->once()
            ->andReturn(false);

        $containerMock->shouldReceive('get')
            ->twice()
            ->with(IOInterface::class)
            ->andReturn($this->ioMock);

        $this->plugin->setContainer($containerMock);
        $this->plugin->onPostUninstall($event);

        $jsonData = \json_decode(\file_get_contents($filePath), true);

        self::assertArrayHasKey('test', $jsonData['scripts']);
        self::assertCount(0, $jsonData['scripts'][ComposerScriptEvents::POST_INSTALL_CMD]);
        self::assertCount(0, $jsonData['scripts'][ComposerScriptEvents::POST_INSTALL_CMD]);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');

        @\unlink($filePath);
        @\unlink($lockfilePath);
    }

    public function testOnPostUninstallWithWithoutDev(): void
    {
        $event = Mockery::mock(PackageEvent::class);

        $event->shouldReceive('isDevMode')
            ->once()
            ->andReturn(false);
        $event->shouldReceive('getComposer->getLocker->getLockData')
            ->once()
            ->andReturn(['packages-dev' => [['name' => Automatic::PACKAGE_NAME]]]);
        $event->shouldReceive('getOperation->getPackage->getName')
            ->never();

        $this->plugin->onPostUninstall($event);
    }

    public function testOnPostUpdatePostMessages(): void
    {
        $this->ioMock->shouldReceive('write')
            ->once();

        $container = Mockery::mock(ContainerContract::class);
        $container->shouldReceive('get')
            ->once()
            ->with(IOInterface::class)
            ->andReturn($this->ioMock);

        $this->plugin->setContainer($container);

        $this->plugin->onPostUpdatePostMessages(Mockery::mock(Event::class));
    }

    public function testInitAutoScripts(): void
    {
        $composerJsonPath = __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'testInitAutoScripts.json';
        $composerLockPath = \mb_substr($composerJsonPath, 0, -4) . 'lock';

        \file_put_contents($composerJsonPath, \json_encode(['test' => []]));
        \file_put_contents($composerLockPath, \json_encode(['packages' => []]));

        \putenv('COMPOSER=' . $composerJsonPath);

        $packageMock = Mockery::mock(PackageInterface::class);
        $packageMock->shouldReceive('getScripts')
            ->once()
            ->andReturn([]);

        $this->composerMock
            ->shouldReceive('getPackage')
            ->once()
            ->andReturn($packageMock);

        $containerMock = $this->arrangeUpdateComposerLock();
        $containerMock->shouldReceive('get')
            ->with(Filesystem::class)
            ->andReturn(new Filesystem());

        $this->plugin->setContainer($containerMock);

        $this->plugin->initAutoScripts();

        $jsonContent = \json_decode(\file_get_contents($composerJsonPath), true);

        self::assertTrue(isset($jsonContent['scripts']));
        self::assertTrue(isset($jsonContent['scripts']['post-install-cmd']));
        self::assertTrue(isset($jsonContent['scripts']['post-update-cmd']));
        self::assertSame('@' . ScriptEvents::AUTO_SCRIPTS, $jsonContent['scripts']['post-install-cmd'][0]);
        self::assertSame('@' . ScriptEvents::AUTO_SCRIPTS, $jsonContent['scripts']['post-update-cmd'][0]);

        $lockContent = \json_decode(\file_get_contents($composerLockPath), true);

        self::assertIsString($lockContent['content-hash']);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');

        @\unlink($composerJsonPath);
        @\unlink($composerLockPath);
    }

    public function testInitAutoScriptsWithAutoScriptInComposerJson(): void
    {
        $packageMock = Mockery::mock(PackageInterface::class);
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

        $containerMock = Mockery::mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->with(Composer::class)
            ->andReturn($this->composerMock);

        $this->plugin->setContainer($containerMock);
        $this->plugin->initAutoScripts();
    }

    public function testOnPostCreateProject(): void
    {
        $composerJsonPath = __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'testOnPostCreateProject.json';
        $composerLockPath = \mb_substr($composerJsonPath, 0, -4) . 'lock';

        \file_put_contents($composerJsonPath, \json_encode([
            'name' => 'narrowspark/narrowspark',
            'type' => 'project',
            'description' => 'A skeleton to start a new Narrowspark project.',
            'license' => 'MIT',
        ]));
        \file_put_contents($composerLockPath, \json_encode(['packages' => []]));

        \putenv('COMPOSER=' . $composerJsonPath);

        $containerMock = $this->arrangeUpdateComposerLock();
        $containerMock->shouldReceive('get')
            ->with('composer-extra')
            ->andReturn([
                'test' => 'foo',
            ]);
        $containerMock->shouldReceive('get')
            ->with(Filesystem::class)
            ->andReturn(new Filesystem());

        $this->plugin->setContainer($containerMock);

        $this->plugin->onPostCreateProject(Mockery::mock(Event::class));

        $jsonContent = \json_decode(\file_get_contents($composerJsonPath), true);

        self::assertFalse(isset($jsonContent['name']));
        self::assertFalse(isset($jsonContent['description']));
        self::assertFalse(isset($jsonContent['description']));
        self::assertSame('proprietary', $jsonContent['license']);
        self::assertTrue(isset($jsonContent['extra']));
        self::assertTrue(isset($jsonContent['extra']['test']));
        self::assertSame('foo', $jsonContent['extra']['test']);

        $lockContent = \json_decode(\file_get_contents($composerLockPath), true);

        self::assertIsString($lockContent['content-hash']);

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

        $containerMock = Mockery::mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->once()
            ->with(Lock::class)
            ->andReturn($this->lockMock);
        $this->plugin->setContainer($containerMock);

        $this->plugin->runSkeletonGenerator(Mockery::mock(Event::class));
    }

    public function testOnPostAutoloadDump(): void
    {
        $containerMock = Mockery::mock(ContainerContract::class);
        $configuratorMock = Mockery::mock(ConfiguratorContract::class);

        NSA::setProperty($this->plugin, 'configuratorsLoaded', false);

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

        $this->plugin->setContainer($containerMock);
        $this->plugin->onPostAutoloadDump(Mockery::mock(Event::class));

        NSA::setProperty($this->plugin, 'configuratorsLoaded', false);
    }

    public function testOnPostAutoloadDumpWithReset(): void
    {
        $containerMock = Mockery::mock(ContainerContract::class);
        $configuratorMock = Mockery::mock(ConfiguratorContract::class);
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

        $event = Mockery::mock(Event::class);

        $this->plugin->setContainer($containerMock);
        $this->plugin->onPostAutoloadDump($event);
        $this->plugin->onPostAutoloadDump($event);
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
            ->with('cache-repo-dir')
            ->andReturn('repo');

        $this->composerMock->shouldReceive('getConfig')
            ->andReturn($this->configMock);
    }

    /**
     * @return \Composer\Repository\RepositoryManager|\Mockery\MockInterface
     */
    private function arrangeLocalRepository()
    {
        $localRepositoryMock = Mockery::mock(WritableRepositoryInterface::class);

        $repositoryMock = Mockery::mock(RepositoryManager::class);
        $repositoryMock->shouldReceive('getLocalRepository')
            ->andReturn($localRepositoryMock);

        return $repositoryMock;
    }

    /**
     * @return \Mockery\MockInterface|\Narrowspark\Automatic\Common\Contract\Container
     */
    private function arrangeUpdateComposerLock()
    {
        $repositoryManagerMock = Mockery::mock(RepositoryManager::class);

        $this->composerMock->shouldReceive('getRepositoryManager')
            ->once()
            ->andReturn($repositoryManagerMock);

        $installationManagerMock = Mockery::mock(InstallationManager::class);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManagerMock);

        $containerMock = Mockery::mock(ContainerContract::class);
        $containerMock->shouldReceive('get')
            ->with(Composer::class)
            ->andReturn($this->composerMock);
        $containerMock->shouldReceive('get')
            ->with(IOInterface::class)
            ->andReturn(new NullIO());

        return $containerMock;
    }

    private function delete(string $path): void
    {
        \array_map(function ($value): void {
            if (\is_dir($value)) {
                $this->delete($value);

                @\rmdir($value);
            } else {
                @\unlink($value);
            }
        }, \glob($path . \DIRECTORY_SEPARATOR . '*'));
    }
}
