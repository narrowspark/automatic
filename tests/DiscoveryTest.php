<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Composer\Composer;
use Composer\Config;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use Composer\Script\ScriptEvents;
use Narrowspark\Discovery\Configurator;
use Narrowspark\Discovery\Discovery;
use Narrowspark\Discovery\Lock;
use Narrowspark\Discovery\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Symfony\Component\Console\Input\InputInterface;

class DiscoveryTest extends MockeryTestCase
{
    use GetGenericPropertyReaderTrait;

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
        self::assertSame(
            [
                'auto-scripts'                        => 'executeAutoScripts',
                PackageEvents::POST_PACKAGE_INSTALL   => 'record',
                PackageEvents::POST_PACKAGE_UPDATE    => 'record',
                PackageEvents::POST_PACKAGE_UNINSTALL => 'record',
                ScriptEvents::POST_INSTALL_CMD        => 'onPostInstall',
                ScriptEvents::POST_UPDATE_CMD         => 'onPostUpdate',
                ScriptEvents::POST_CREATE_PROJECT_CMD => 'onPostCreateProject',
            ],
            Discovery::getSubscribedEvents()
        );
    }

    public function testActivate(): void
    {
        $discovery    = new Discovery();
        $composerMock = $this->mock(Composer::class);
        $configMock   = $this->mock(Config::class);
        $ioMock       = $this->mock(IOInterface::class);

        $configMock->shouldReceive('get')
            ->once()
            ->with('vendor-dir')
            ->andReturn(__DIR__);
        $composerMock->shouldReceive('getConfig')
            ->andReturn($configMock);

        $ioMock->shouldReceive('writeError');

        $rootPackageMock = $this->mock(RootPackageInterface::class);
        $rootPackageMock->shouldReceive('getExtra')
            ->andReturn([]);
        $rootPackageMock->shouldReceive('getMinimumStability')
            ->once()
            ->andReturn('stable');

        $composerMock->shouldReceive('getPackage')
            ->twice()
            ->andReturn($rootPackageMock);

        $localRepositoryMock = $this->mock(WritableRepositoryInterface::class);
        $localRepositoryMock->shouldReceive('getPackages')
            ->once()
            ->andReturn([]);

        $repositoryMock = $this->mock(RepositoryManager::class);
        $repositoryMock->shouldReceive('getLocalRepository')
            ->andReturn($localRepositoryMock);

        $composerMock->shouldReceive('getRepositoryManager')
            ->andReturn($repositoryMock);

        $input = &$this->getGenericPropertyReader()($ioMock, 'input');
        $input = $this->mock(InputInterface::class);

        $discovery->activate($composerMock, $ioMock);

        self::assertInstanceOf(Lock::class, $discovery->getLock());
        self::assertInstanceOf(Configurator::class, $discovery->getConfigurator());

        self::assertSame(
            [
                'This file locks the discovery information of your project to a known state',
                'This file is @generated automatically',
            ],
            $discovery->getLock()->get('_readme')
        );
    }
}
