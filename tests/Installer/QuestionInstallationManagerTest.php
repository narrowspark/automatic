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
     * @var string
     */
    private $manipulatedComposerPath;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->composerCachePath = __DIR__ . '/cache';
        $this->manipulatedComposerPath = $this->composerCachePath . '/manipulated_composer.json';

        \mkdir($this->composerCachePath);

        \putenv('COMPOSER_CACHE_DIR=' . $this->composerCachePath);

        parent::setUp();

        \file_put_contents(
            $this->manipulatedComposerPath,
            '{
    "name": "manipulated/test",
    "authors": [
        {
            "name": "Daniel Bannert",
            "email": "d.bannert@anolilab.de"
        }
    ],
    "require": {}
}'
        );

        $this->ioMock->shouldReceive('hasAuthentication')
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
        $jsonData = $this->getComposerJsonData();

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
        $jsonData = $this->getComposerJsonData();

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
        $jsonData = $this->getComposerJsonData();

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

    /**
     * @expectedException \Narrowspark\Discovery\Common\Exception\InvalidArgumentException
     * @expectedExceptionMessage Could not find package viserio/routing at any version for your minimum-stability (stable). Check the package spelling or your minimum-stability.
     */
    public function testInstallThrowsExceptionWhenNoVersionIsFound(): void
    {
        $jsonData = \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/composer_without_version.json'), true);

        $this->arrangeDownloadAndWritePackagistData();

        $rootPackageMock = $this->setupRootPackage([], 'stable', [], []);

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($rootPackageMock);

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(true);

        $this->composerMock->shouldReceive('setInstallationManager')
            ->once();

        $questionInstallationManager = $this->getQuestionInstallationManager();

        $installationManager = $this->mock(InstallationManager::class);
        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManager);

        $questionInstallationManager->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);
    }

    public function testInstallWithPackageNameAndVersionWithStablePackageVersions(): void
    {
        $jsonData        = $this->getComposerJsonData();
        $rootPackageMock = $this->setupRootPackage([], 'stable', [], []);

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($rootPackageMock);

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(true);

        $this->arrangeInstallationManager();

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

        $this->arrangeVendorConfig();

        $packages = $questionInstallationManager->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);

        $this->assertPackagesInstall($packages, 'dev-master');
    }

    public function testInstallSkipPackageInstallIfPackageIsInRootPackage(): void
    {
        $jsonData = $this->getComposerJsonData();
        $requires = [
            'viserio/routing' => new Link(
                '__root__',
                'viserio/routing',
                (new VersionParser())->parseConstraints('dev-master'),
                'requires',
                'dev-master'
            ),
        ];

        $rootPackageMock = $this->setupRootPackage([], 'stable', $requires, []);

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($rootPackageMock);

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(true);

        $this->arrangeInstallationManager();

        $this->ioMock->shouldReceive('askAndValidate')
            ->never()
            ->with(
                '<question>this is a question</question>
  [<comment>0</comment>] viserio/routing : dev-master
  [<comment>1</comment>] viserio/view : dev-master
  Make your selection: ',
                \Mockery::type(\Closure::class)
            )
            ->andReturn('viserio/routing');
        $this->ioMock->shouldReceive('writeError')
            ->never()
            ->with('Using version <info>dev-master</info> for <info>viserio/routing</info>');
        $this->ioMock->shouldReceive('writeError')
            ->never()
            ->with('Updating composer.json');

        $this->ioMock->shouldReceive('writeError')
            ->never()
            ->with('Updating root package');

        $rootPackageMock->shouldReceive('setRequires')
            ->never()
            ->with($requires);

        $this->ioMock->shouldReceive('writeError')
            ->never()
            ->with('Running an update to install dependent packages');

        $this->composerMock->shouldReceive('setPackage')
            ->never()
            ->with($rootPackageMock);

        $installationManager = $this->mock(InstallationManager::class);
        $installationManager->shouldReceive('getOperations')
            ->once()
            ->andReturn([]);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManager);

        $questionInstallationManager = $this->getQuestionInstallationManager();

        $this->arrangeVendorConfig();

        $packages = $questionInstallationManager->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);

        self::assertCount(0, $packages);
    }

    public function testInstallWithPackageNameAndVersionWithDevPackageVersions(): void
    {
        $jsonData = \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/composer_without_version.json'), true);

        $rootPackageMock = $this->setupRootPackage([], 'dev', [], []);

        $this->arrangeDownloadAndWritePackagistData();

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($rootPackageMock);

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(true);

        $this->arrangeInstallationManager();

        $questionInstallationManager = $this->getQuestionInstallationManager();

        $versionSelector       = $questionInstallationManager->getVersionSelector();
        $packageName           = 'viserio/routing';
        $routingPackageVersion = $versionSelector->findRecommendedRequireVersion($versionSelector->findBestCandidate($packageName));
        $viewPackageVersion    = $versionSelector->findRecommendedRequireVersion($versionSelector->findBestCandidate('viserio/view'));

        $this->ioMock->shouldReceive('askAndValidate')
            ->once()
            ->with(
                '<question>this is a question</question>
  [<comment>0</comment>] viserio/routing : ' . $routingPackageVersion . '
  [<comment>1</comment>] viserio/view : ' . $viewPackageVersion . '
  Make your selection: ',
                \Mockery::type(\Closure::class)
            )
            ->andReturn($packageName);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Using version <info>' . $routingPackageVersion . '</info> for <info>viserio/routing</info>');
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
                    (new VersionParser())->parseConstraints($routingPackageVersion),
                    'requires',
                    $routingPackageVersion
                ),
            ]);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Running an update to install dependent packages');

        $this->composerMock->shouldReceive('setPackage')
            ->once()
            ->with($rootPackageMock);

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

        $this->arrangeVendorConfig();

        $packages = $questionInstallationManager->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);

        $this->assertPackagesInstall($packages, $routingPackageVersion);
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

        $rootPackageMock->shouldReceive('getExtra')
            ->andReturn($extra);
        $rootPackageMock->shouldReceive('getMinimumStability')
            ->andReturn($stability);
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
        $manager = new MockedQuestionInstallationManager(
            $this->composerMock,
            $this->ioMock,
            $this->inputMock
        );

        $manager->setComposerFile($this->manipulatedComposerPath);

        return $manager;
    }

    private function arrangeDownloadAndWritePackagistData(): void
    {
        $this->ioMock->shouldReceive('writeError')
            ->with(
                \Mockery::on(function ($argument) {
                    return \mb_strpos($argument, 'Downloading') !== false;
                }),
                true,
                IOInterface::DEBUG
            );
        $this->ioMock->shouldReceive('writeError')
            ->with(
                \Mockery::on(function ($argument) {
                    return \mb_strpos($argument, 'Writing') !== false;
                }),
                true,
                IOInterface::DEBUG
            );
    }

    private function arrangeInstallationManager(): void
    {
        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($this->mock(BaseInstallationManager::class));

        $this->composerMock->shouldReceive('setInstallationManager')
            ->twice();
    }

    /**
     * @return array
     */
    private function getComposerJsonData(): array
    {
        return \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/composer.json'), true);
    }

    /**
     * @return array
     */
    private function getManipulatedComposerJsonData(): array
    {
        return \json_decode(\file_get_contents($this->manipulatedComposerPath), true);
    }

    private function arrangeVendorConfig(): void
    {
        $this->configMock->shouldReceive('get')
            ->once()
            ->with('vendor-dir')
            ->andReturn(__DIR__);
        $this->composerMock->shouldReceive('getConfig')
            ->andReturn($this->configMock);
    }

    /**
     * @param array $packages
     * @param string $version
     */
    private function assertPackagesInstall(array $packages, string $version): void
    {
        $jsonData = $this->getManipulatedComposerJsonData();

        self::assertSame(['viserio/routing' => $version], $jsonData['require']);
        self::assertCount(1, $packages);
    }
}
