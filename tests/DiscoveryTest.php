<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\WritableRepositoryInterface;
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

    public function testActivate(): void
    {
        $this->allowMockingNonExistentMethods(true);

        $disovery = new Discovery();
        $composer = $this->mock(Composer::class);
        $ioMock   = $this->mock(IOInterface::class);

        $ioMock->shouldReceive('writeError');

        $rootPackageMock = $this->mock(RootPackageInterface::class);
        $rootPackageMock->shouldReceive('getExtra')
            ->andReturn([]);
        $rootPackageMock->shouldReceive('getMinimumStability')
            ->once()
            ->andReturn('stable');

        $composer->shouldReceive('getPackage')
            ->twice()
            ->andReturn($rootPackageMock);

        $configMock = $this->mock(Config::class);
        $configMock->shouldReceive('get')
            ->once()
            ->with('vendor-dir')
            ->andReturn(__DIR__);

        $composer->shouldReceive('getConfig')
            ->once()
            ->andReturn($configMock);

        $repositoryMock = $this->mock(RepositoryInterface::class);

        $localRepositoryMock = $this->mock(WritableRepositoryInterface::class);
        $localRepositoryMock->shouldReceive('getPackages')
            ->once()
            ->andReturn([]);

        $repositoryMock->shouldReceive('getLocalRepository')
            ->andReturn($localRepositoryMock);

        $composer->shouldReceive('getRepositoryManager')
            ->andReturn($repositoryMock);

        $input = &$this->getGenericPropertyReader()($ioMock, 'input');
        $input = $this->mock(InputInterface::class);

        $disovery->activate($composer, $ioMock);

        self::assertInstanceOf(Lock::class, $disovery->getLock());
        self::assertInstanceOf(Configurator::class, $disovery->getConfigurator());

        self::assertSame(
            [
                'This file locks the discovery information of your project to a known state',
                'This file is @generated automatically',
            ],
            $disovery->getLock()->get('_readme')
        );
        self::assertInternalType('string', $disovery->getLock()->get('content-hash'));

        $this->allowMockingNonExistentMethods();
    }
}
