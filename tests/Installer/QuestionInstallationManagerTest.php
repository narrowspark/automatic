<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Installer;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\Installer;
use Composer\Installer\InstallationManager as BaseInstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use Composer\Semver\VersionParser;
use Mockery\MockInterface;
use Narrowspark\Discovery\Installer\InstallationManager;
use Narrowspark\Discovery\Test\Fixtures\MockedQuestionInstallationManager;
use Narrowspark\Discovery\Traits\GetGenericPropertyReaderTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @runInSeparateProcess
 */
class QuestionInstallationManagerTest extends AbstractInstallerTestCase
{
    use GetGenericPropertyReaderTrait;

    /**
     * @var string
     */
    private $composerCachePath;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->composerCachePath = __DIR__ . '/cache';

        \mkdir($this->composerCachePath);

        \putenv('COMPOSER_CACHE_DIR=' . $this->composerCachePath);

        parent::setUp();

        $this->ioMock->shouldReceive('hasAuthentication')
            ->once()
            ->andReturn(false);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Downloading https://packagist.org/packages.json', true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Writing ' . $this->composerCachePath . '/repo/https---packagist.org/packages.json into cache', true, IOInterface::DEBUG);

        $packageMock = $this->mock(Package::class);
        $packageMock->shouldReceive('getName')
            ->once()
            ->andReturn('foo/bar');
        $packageMock->shouldReceive('getPrettyVersion')
            ->once()
            ->andReturn('dev-master');
        $packageMock->shouldReceive('setRepository');

        $localRepositoryMock = $this->mock(WritableRepositoryInterface::class);
        $localRepositoryMock->shouldReceive('getPackages')
            ->once()
            ->andReturn([$packageMock]);

        $repositoryMock = $this->mock(RepositoryManager::class);
        $repositoryMock->shouldReceive('getLocalRepository')
            ->once()
            ->andReturn($localRepositoryMock);

        $this->composerMock->shouldReceive('getRepositoryManager')
            ->once()
            ->andReturn($repositoryMock);
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

    public function testInstallOnDisabledInteractive(): void
    {
        $jsonData = \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/composer.json'), true);

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(false);

        $rootPackageMock = $this->setupRootPackage([], 'stable', [], []);

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($rootPackageMock);

        $questionInstallationManager = $this->getQuestionInstallationManager();

        $packages = $questionInstallationManager->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);

        self::assertCount(0, $packages);
    }

    public function testInstallWithEmptyDependencies(): void
    {
        $jsonData = \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/composer.json'), true);

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(true);

        $rootPackageMock = $this->setupRootPackage([], 'stable', [], []);

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($rootPackageMock);

        $questionInstallationManager = $this->getQuestionInstallationManager();

        $packages = $questionInstallationManager->install($jsonData['name'], []);

        self::assertCount(0, $packages);
    }

    /**
     * @expectedException \Narrowspark\Discovery\Common\Exception\RuntimeException
     * @expectedExceptionMessage You must provide at least two optional dependencies.
     */
    public function testInstallWithAEmptyQuestion(): void
    {
        $jsonData = \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/composer.json'), true);

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(true);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($this->mock(BaseInstallationManager::class));

        $this->composerMock->shouldReceive('setInstallationManager')
            ->once();

        $rootPackageMock = $this->setupRootPackage([], 'stable', [], []);

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($rootPackageMock);

        $questionInstallationManager = $this->getQuestionInstallationManager();

        $questionInstallationManager->install($jsonData['name'], ['this is a question' => []]);
    }

    public function testInstallOnEnabledInteractiveAndWithKeyValueAnswer(): void
    {
        $jsonData = \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/composer.json'), true);

        $rootPackageMock = $this->setupRootPackage([], 'stable', [], []);

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($rootPackageMock);

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(true);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($this->mock(BaseInstallationManager::class));

        $this->composerMock->shouldReceive('setInstallationManager')
            ->twice();

        $this->ioMock->shouldReceive('askAndValidate')
            ->once()
            ->with(
                '<question>this is a question</question>
  [<comment>0</comment>] viserio/routing : dev-master
  [<comment>1</comment>] viserio/view : dev-master
  Make your selection: ',
                \Mockery::type(\Closure::class)
            )
            ->andReturn('viserio/routing');
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Using version <info>dev-master</info> for <info>viserio/routing</info>');
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Updating composer.json');

        $this->configMock->shouldReceive('get')
            ->once()
            ->with('sort-packages')
            ->andReturn(true);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Updating root package');

        $rootPackageMock->shouldReceive('setRequires')
            ->once()
            ->with([
                'viserio/routing' => new Link(
                    '__root__',
                    'viserio/routing',
                    (new VersionParser())->parseConstraints('dev-master'),
                    'requires',
                    'dev-master'
                ),
            ]);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Running an update to install dependent packages');

        $this->composerMock->shouldReceive('setPackage')
            ->once()
            ->with($rootPackageMock);

        $questionInstallationManager = $this->getQuestionInstallationManager();

        $installer = &$this->getGenericPropertyReader()($questionInstallationManager, 'installer');
        $installer = $this->arrangeInstaller(['viserio/routing']);

        $composerPackage = $this->arrangeComposerPackage($jsonData);

        $operation = $this->mock(InstallOperation::class);
        $operation->shouldReceive('getPackage')
            ->once()
            ->andReturn($composerPackage);

        $installationManager = $this->mock(InstallationManager::class);
        $installationManager->shouldReceive('getOperations')
            ->once()
            ->andReturn([$operation]);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManager);

        $this->composerMock->shouldReceive('getConfig')
            ->once()
            ->andReturn($this->configMock);

        $packages = $questionInstallationManager->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);

        self::assertCount(1, $packages);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    /**
     * @param array $with
     * @param int   $run
     *
     * @return \Composer\Installer|\Mockery\MockInterface
     */
    private function arrangeInstaller(array $with, int $run = 0): MockInterface
    {
        $installer = $this->mock(Installer::class);
        $installer->shouldReceive('setUpdateWhitelist')
            ->once()
            ->with($with);
        $installer->shouldReceive('run')
            ->once()
            ->andReturn($run);

        return $installer;
    }

    /**
     * @param array $jsonData
     *
     * @return \Composer\Package\PackageInterface|\Mockery\MockInterface
     */
    private function arrangeComposerPackage(array $jsonData): MockInterface
    {
        $composerPackage = $this->mock(PackageInterface::class);
        $composerPackage->shouldReceive('getExtra')
            ->andReturn($jsonData['extra']);
        $composerPackage->shouldReceive('getName')
            ->andReturn($jsonData['name']);
        $composerPackage->shouldReceive('getRequires')
            ->andReturn($jsonData['require']);
        $composerPackage->shouldReceive('getPrettyVersion')
            ->andReturn('dev-master');
        $composerPackage->shouldReceive('getSourceUrl')
            ->andReturn(null);
        $composerPackage->shouldReceive('getType')
            ->andReturn(null);

        return $composerPackage;
    }

    /**
     * @param array       $extra
     * @param null|string $stability
     * @param array       $requires
     * @param array       $devRequires
     *
     * @return \Composer\Package\RootPackageInterface|\Mockery\MockInterface
     */
    private function setupRootPackage(array $extra, ?string $stability, array $requires, array $devRequires): MockInterface
    {
        $rootPackageMock = $this->mock(RootPackageInterface::class);

        $rootPackageMock->shouldReceive('getMinimumStability')
            ->andReturn('stable');
        $rootPackageMock->shouldReceive('getExtra')
            ->andReturn($extra);
        $rootPackageMock->shouldReceive('getRequires')
            ->andReturn($requires);
        $rootPackageMock->shouldReceive('getDevRequires')
            ->andReturn($devRequires);

        return $rootPackageMock;
    }

    /**
     * @return \Narrowspark\Discovery\Test\Fixtures\MockedQuestionInstallationManager
     */
    private function getQuestionInstallationManager(): MockedQuestionInstallationManager
    {
        return new MockedQuestionInstallationManager(
            $this->composerMock,
            $this->ioMock,
            $this->inputMock,
            __DIR__
        );
    }
}
