<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Installer;

use Composer\Downloader\DownloadManager;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Narrowspark\Discovery\Installer\ConfiguratorInstaller;
use Narrowspark\Discovery\Lock;
use Narrowspark\Discovery\Test\Traits\ArrangeComposerClasses;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

class ConfiguratorInstallerTest extends MockeryTestCase
{
    use ArrangeComposerClasses;

    /**
     * @var \Mockery\MockInterface|\Narrowspark\Discovery\Lock
     */
    private $lockMock;

    /**
     * @var \Composer\Repository\InstalledRepositoryInterface|\Mockery\MockInterface
     */
    private $repositoryMock;

    /**
     * @var \Composer\Package\PackageInterface|\Mockery\MockInterface
     */
    private $packageMock;

    /**
     * @var \Narrowspark\Discovery\Installer\ConfiguratorInstaller
     */
    private $configuratorInstaller;

    /**
     * @var \Composer\Downloader\DownloadManager|\Mockery\MockInterface
     */
    private $downloadManagerMock;

    /**
     * @var string
     */
    private $composerJsonPath;

    /**
     * @var string
     */
    private $configuratorPath;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composerJsonPath = __DIR__ . '/composer.json';
        $this->configuratorPath = __DIR__ . '/../Fixtures/Configurator';

        $this->arrangeComposerClasses();

        $this->lockMock = $this->mock(Lock::class);

        $this->configMock->shouldReceive('get')
            ->once()
            ->with('vendor-dir')
            ->andReturn($this->configuratorPath);
        $this->configMock->shouldReceive('get')
            ->once()
            ->with('bin-dir')
            ->andReturn(__DIR__);
        $this->configMock->shouldReceive('get')
            ->once()
            ->with('bin-compat')
            ->andReturn(__DIR__);

        $this->composerMock->shouldReceive('getConfig')
            ->andReturn($this->configMock);

        $this->downloadManagerMock = $this->mock(DownloadManager::class);

        $this->composerMock->shouldReceive('getDownloadManager')
            ->once()
            ->andReturn($this->downloadManagerMock);

        $this->configuratorInstaller = new ConfiguratorInstaller($this->ioMock, $this->composerMock, $this->lockMock);

        $this->repositoryMock = $this->mock(InstalledRepositoryInterface::class);
        $this->packageMock    = $this->mock(PackageInterface::class);
    }

    public function testSupports(): void
    {
        self::assertTrue($this->configuratorInstaller->supports('discovery-configurator'));
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Error while installing "prisis/test", discovery-configurator packages should have a namespace defined in their psr4 key to be usable.
     */
    public function testInstallWithEmptyPsr4(): void
    {
        $this->packageMock->shouldReceive('getAutoload')
            ->once()
            ->andReturn(['psr-4' => []]);
        $this->packageMock->shouldReceive('getPrettyName')
            ->once()
            ->andReturn('prisis/test');

        $this->configuratorInstaller->install($this->repositoryMock, $this->packageMock);
    }

    public function testInstall(): void
    {
        $name = 'prisis/install';

        $this->packageMock->shouldReceive('getAutoload')
            ->once()
            ->andReturn(['psr-4' => ['Test\\' => '']]);
        $this->packageMock->shouldReceive('getPrettyName')
            ->times(4)
            ->andReturn($name);

        $this->packageMock->shouldReceive('getTargetDir')
            ->andReturn(null);
        $this->packageMock->shouldReceive('getBinaries')
            ->andReturn([]);

        $this->repositoryMock->shouldReceive('hasPackage')
            ->once()
            ->with($this->packageMock)
            ->andReturn(true);

        $this->downloadManagerMock->shouldReceive('download');

        $this->lockMock->shouldReceive('has')
            ->once()
            ->with(ConfiguratorInstaller::LOCK_KEY)
            ->andReturn(false);
        $this->lockMock->shouldReceive('add')
            ->once()
            ->with(ConfiguratorInstaller::LOCK_KEY, \Mockery::type('array'));

        $this->configuratorInstaller->install($this->repositoryMock, $this->packageMock);
    }

    public function testUpdate(): void
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
            ->with(ConfiguratorInstaller::LOCK_KEY)
            ->andReturn(true);
        $this->lockMock->shouldReceive('get')
            ->once()
            ->with(ConfiguratorInstaller::LOCK_KEY)
            ->andReturn([]);
        $this->lockMock->shouldReceive('add')
            ->once()
            ->with(ConfiguratorInstaller::LOCK_KEY, \Mockery::type('array'));

        $this->configuratorInstaller->update($this->repositoryMock, $this->packageMock, $targetPackage);
    }
}
