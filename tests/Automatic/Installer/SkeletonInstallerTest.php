<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Installer;

use Composer\Package\PackageInterface;
use Narrowspark\Automatic\Installer\SkeletonInstaller;

/**
 * @internal
 */
final class SkeletonInstallerTest extends AbstractInstallerTest
{
    /**
     * {@inheritdoc}
     */
    protected $installerClass = SkeletonInstaller::class;

    public function testUpdateWithOverwrite(): void
    {
        $name = 'prisis/update';

        $targetPackage = $this->mock(PackageInterface::class);

        $this->repositoryMock->shouldReceive('hasPackage')
            ->andReturn(true);

        $this->packageMock->shouldReceive('getBinaries')
            ->andReturn([]);
        $this->packageMock->shouldReceive('getPrettyName')
            ->andReturn($name);
        $this->packageMock->shouldReceive('getTargetDir')
            ->andReturn('');

        $targetPackage->shouldReceive('getPrettyName')
            ->andReturn($name);
        $targetPackage->shouldReceive('getTargetDir')
            ->andReturn('');
        $targetPackage->shouldReceive('getBinaries')
            ->andReturn([]);

        $this->downloadManagerMock->shouldReceive('update');

        $this->repositoryMock->shouldReceive('removePackage');

        $targetPackage->shouldReceive('getAutoload')
            ->andReturn(['psr-4' => ['Test\\' => '']]);

        $this->lockMock->shouldReceive('has')
            ->once()
            ->with($this->installerClass::LOCK_KEY)
            ->andReturn(true);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(\sprintf('Automatic lock key [%s] was overwritten.', $this->installerClass::LOCK_KEY));

        $this->lockMock->shouldReceive('remove')
            ->once()
            ->with($this->installerClass::LOCK_KEY);

        $this->lockMock->shouldReceive('add')
            ->once()
            ->with($this->installerClass::LOCK_KEY, \Mockery::type('array'));

        $this->configuratorInstaller->update($this->repositoryMock, $this->packageMock, $targetPackage);
    }
}
