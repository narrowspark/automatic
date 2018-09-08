<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Installer;

use Composer\Downloader\DownloadManager;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\ClassFinder;
use Narrowspark\Automatic\Common\Contract\Exception\UnexpectedValueException;
use Narrowspark\Automatic\Lock;
use Narrowspark\Automatic\Test\Traits\ArrangeComposerClasses;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 */
abstract class AbstractInstallerTest extends MockeryTestCase
{
    use ArrangeComposerClasses;

    /**
     * @var \Composer\Repository\InstalledRepositoryInterface|\Mockery\MockInterface
     */
    protected $repositoryMock;

    /**
     * @var \Composer\Package\PackageInterface|\Mockery\MockInterface
     */
    protected $packageMock;

    /**
     * @var \Composer\Package\PackageInterface|\Mockery\MockInterface
     */
    protected $targetPackageMock;

    /**
     * @var \Narrowspark\Automatic\Installer\AbstractInstaller
     */
    protected $configuratorInstaller;

    /**
     * @var \Composer\Downloader\DownloadManager|\Mockery\MockInterface
     */
    protected $downloadManagerMock;

    /**
     * @var string
     */
    protected $composerJsonPath;

    /**
     * @var string
     */
    protected $configuratorPath;

    /**
     * @var \Narrowspark\Automatic\Installer\AbstractInstaller
     */
    protected $installerClass;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composerJsonPath = __DIR__ . '/composer.json';
        $this->configuratorPath = __DIR__ . '/../Fixture/Configurator';

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

        $this->configuratorInstaller = new $this->installerClass($this->ioMock, $this->composerMock, $this->lockMock, new ClassFinder($this->configuratorPath));

        $this->repositoryMock    = $this->mock(InstalledRepositoryInterface::class);
        $this->packageMock       = $this->mock(PackageInterface::class);
        $this->targetPackageMock = $this->mock(PackageInterface::class);
    }

    public function testSupports(): void
    {
        static::assertTrue($this->configuratorInstaller->supports($this->installerClass::TYPE));
    }

    public function testInstallWithEmptyPsr4(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Error while installing [prisis/test], ' . $this->installerClass::TYPE . ' packages should have a namespace defined in their psr4 key to be usable.');

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
            ->times(3)
            ->andReturn($name);
        $this->packageMock->shouldReceive('getName')
            ->times(3)
            ->andReturn(\trim($name, '/'));

        $this->packageMock->shouldReceive('getTargetDir')
            ->andReturn(null);
        $this->packageMock->shouldReceive('getBinaries')
            ->andReturn([]);

        $this->repositoryMock->shouldReceive('hasPackage')
            ->once()
            ->with($this->packageMock)
            ->andReturn(true);

        $this->downloadManagerMock->shouldReceive('download');

        $this->lockMock->shouldReceive('addSub')
            ->with($this->installerClass::LOCK_KEY, $name, \Mockery::type('array'));
        $this->lockMock->shouldReceive('addSub')
            ->with(Automatic::LOCK_CLASSMAP, $name, \Mockery::type('array'));

        $this->configuratorInstaller->install($this->repositoryMock, $this->packageMock);
    }

    public function testUpdate(): void
    {
        $name = 'prisis/update';

        $this->repositoryMock->shouldReceive('hasPackage')
            ->andReturn(true);

        $this->packageMock->shouldReceive('getBinaries')
            ->andReturn([]);
        $this->packageMock->shouldReceive('getTargetDir')
            ->andReturn('');
        $this->packageMock->shouldReceive('getPrettyName')
            ->andReturn($name);
        $this->packageMock->shouldReceive('getName')
            ->andReturn(\trim($name, '/'));

        $this->targetPackageMock->shouldReceive('getPrettyName')
            ->andReturn($name);
        $this->targetPackageMock->shouldReceive('getName')
            ->andReturn(\trim($name, '/'));
        $this->targetPackageMock->shouldReceive('getTargetDir')
            ->andReturn('');
        $this->targetPackageMock->shouldReceive('getBinaries')
            ->andReturn([]);

        $this->downloadManagerMock->shouldReceive('update');

        $this->repositoryMock->shouldReceive('removePackage');

        $this->targetPackageMock->shouldReceive('getAutoload')
            ->andReturn(['psr-4' => ['Test\\' => '']]);

        $this->lockMock->shouldReceive('addSub')
            ->with($this->installerClass::LOCK_KEY, $name, \Mockery::type('array'));
        $this->lockMock->shouldReceive('addSub')
            ->with(Automatic::LOCK_CLASSMAP, $name, \Mockery::type('array'));

        $this->configuratorInstaller->update($this->repositoryMock, $this->packageMock, $this->targetPackageMock);
    }

    public function testInstallWithNotFoundClasses(): void
    {
        $name = 'prisis/empty';

        $this->packageMock->shouldReceive('getAutoload')
            ->once()
            ->andReturn(['psr-4' => ['NoTest\\' => '']]);
        $this->packageMock->shouldReceive('getPrettyName')
            ->andReturn($name);

        $this->packageMock->shouldReceive('getTargetDir')
            ->andReturn(null);
        $this->packageMock->shouldReceive('getBinaries')
            ->andReturn([]);
        $this->packageMock->shouldReceive('getName')
            ->andReturn(\trim($name, '/'));

        $this->repositoryMock->shouldReceive('hasPackage')
            ->twice()
            ->with($this->packageMock)
            ->andReturn(true);

        $this->downloadManagerMock->shouldReceive('download')
            ->once();

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Installation failed, rolling back');

        $this->downloadManagerMock->shouldReceive('remove')
            ->once();

        $this->repositoryMock->shouldReceive('removePackage')
            ->once();

        $this->lockMock->shouldReceive('remove')
            ->with($this->installerClass::LOCK_KEY, $name);
        $this->lockMock->shouldReceive('remove')
            ->with(Automatic::LOCK_CLASSMAP, $name);

        $this->configuratorInstaller->install($this->repositoryMock, $this->packageMock);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
