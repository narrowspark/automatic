<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Installer;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\MarkAliasInstalledOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\InstallerInterface;
use Composer\Package\Package;
use Composer\Repository\InstalledRepositoryInterface;
use Narrowspark\Discovery\Installer\InstallationManager;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

class InstallationManagerTest extends MockeryTestCase
{
    public function testExecuteWithGetOperations(): void
    {
        $manager = new InstallationManager();

        $installerMock = $this->mock(InstallerInterface::class);
        $installerMock->shouldReceive('supports')
            ->andReturn(true);
        $installerMock->shouldReceive('update');
        $installerMock->shouldReceive('install');
        $installerMock->shouldReceive('uninstall');

        $manager->addInstaller($installerMock);

        $installedRepoMock = $this->mock(InstalledRepositoryInterface::class);
        $installedRepoMock->shouldReceive('hasPackage');
        $installedRepoMock->shouldReceive('addPackage');

        $packageMock = $this->mock(Package::class);
        $packageMock->shouldReceive('getType')
            ->andReturn('library');
        $packageMock->shouldReceive('getNotificationUrl')
            ->andReturn('');

        $updateOperationMock = $this->mock(UpdateOperation::class)->makePartial();
        $updateOperationMock->shouldReceive('getInitialPackage')
            ->andReturn($packageMock);
        $updateOperationMock->shouldReceive('getTargetPackage')
            ->andReturn($packageMock);

        $uninstallOperationMock = $this->mock(UninstallOperation::class)->makePartial();
        $uninstallOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        $installOperationMock = $this->mock(InstallOperation::class)->makePartial();
        $installOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        $aliasOperationMock = $this->mock(MarkAliasInstalledOperation::class)->makePartial();
        $aliasOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        $manager->execute($installedRepoMock, $updateOperationMock);
        $manager->execute($installedRepoMock, $uninstallOperationMock);
        $manager->execute($installedRepoMock, $installOperationMock);
        $manager->execute($installedRepoMock, $aliasOperationMock);

        self::assertCount(3, $manager->getOperations());
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
