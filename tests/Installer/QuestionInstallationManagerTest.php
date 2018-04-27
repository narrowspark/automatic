<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Installer;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Installer;
use Composer\Installer\InstallationManager as BaseInstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use Composer\Semver\VersionParser;
use Mockery\MockInterface;
use Narrowspark\Discovery\Installer\InstallationManager;
use Narrowspark\Discovery\Test\Fixtures\MockedQuestionInstallationManager;
use Symfony\Component\Filesystem\Filesystem;

class QuestionInstallationManagerTest extends AbstractInstallerTestCase
{
    /**
     * @var string
     */
    private $composerCachePath;

    /**
     * @var string
     */
    private $manipulatedComposerPath;

    /**
     * @var string
     */
    private $composerJsonWithRequiresPath;

    /**
     * @var \Composer\Repository\WritableRepositoryInterface|\Mockery\MockInterface
     */
    private $localRepositoryMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->composerCachePath = __DIR__ . '/cache';

        \mkdir($this->composerCachePath);
        \putenv('COMPOSER_CACHE_DIR=' . $this->composerCachePath);

        parent::setUp();

        $this->createManipulatedComposer();
        $this->createComposerJsonWithRequires();

        $this->ioMock->shouldReceive('hasAuthentication')
            ->andReturn(false);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Downloading https://packagist.org/packages.json', true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Writing ' . $this->composerCachePath . '/repo/https---packagist.org/packages.json into cache', true, IOInterface::DEBUG);

        $this->localRepositoryMock = $this->mock(WritableRepositoryInterface::class);

        $repositoryMock = $this->mock(RepositoryManager::class);
        $repositoryMock->shouldReceive('getLocalRepository')
            ->once()
            ->andReturn($this->localRepositoryMock);

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
        $jsonData = $this->getFixturesComposerJsonData();

        $this->localRepositoryMock->shouldReceive('getPackages')
            ->once()
            ->andReturn([]);

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(false);

        $this->arrangeSimpleRootPackage('stable');

        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);

        $packages = $questionInstallationManager->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);

        self::assertCount(0, $packages);
    }

    public function testInstallWithEmptyDependencies(): void
    {
        $jsonData = $this->getFixturesComposerJsonData();

        $this->localRepositoryMock->shouldReceive('getPackages')
            ->once()
            ->andReturn([]);

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(true);

        $this->arrangeSimpleRootPackage('stable');

        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);

        $packages = $questionInstallationManager->install($jsonData['name'], []);

        self::assertCount(0, $packages);
    }

    /**
     * @expectedException \Narrowspark\Discovery\Common\Exception\RuntimeException
     * @expectedExceptionMessage You must provide at least two optional dependencies.
     */
    public function testInstallWithAEmptyQuestion(): void
    {
        $jsonData = $this->getFixturesComposerJsonData();

        $this->localRepositoryMock->shouldReceive('getPackages')
            ->once()
            ->andReturn([]);

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(true);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($this->mock(BaseInstallationManager::class));

        $this->composerMock->shouldReceive('setInstallationManager')
            ->once();

        $this->arrangeSimpleRootPackage('stable');

        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);

        $questionInstallationManager->install($jsonData['name'], ['this is a question' => []]);
    }

    /**
     * @expectedException \Narrowspark\Discovery\Common\Exception\InvalidArgumentException
     * @expectedExceptionMessage Could not find package viserio/routing at any version for your minimum-stability (stable). Check the package spelling or your minimum-stability.
     */
    public function testInstallThrowsExceptionWhenNoVersionIsFound(): void
    {
        $jsonData = $this->getFixturesComposerJsonWithoutVersionData();

        $this->localRepositoryMock->shouldReceive('getPackages')
            ->once()
            ->andReturn([]);

        $this->arrangeDownloadAndWritePackagistData();

        $this->arrangeSimpleRootPackage('stable');

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(true);

        $this->composerMock->shouldReceive('setInstallationManager')
            ->once();

        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);

        $installationManager = $this->mock(InstallationManager::class);
        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManager);

        $questionInstallationManager->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);
    }

    public function testInstallWithPackageNameAndVersionWithStablePackageVersions(): void
    {
        $jsonData = $this->getFixturesComposerJsonData();

        $this->localRepositoryMock->shouldReceive('getPackages')
            ->once()
            ->andReturn([]);

        $rootPackageMock = $this->arrangeSimpleRootPackage('stable');

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

        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);

        $questionInstallationManager->setInstaller($this->arrangeInstaller(['viserio/routing']));

        $composerPackage = $this->arrangeComposerPackage(['name' => 'prisis/test', 'version' => 'dev-master']);

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

        $packages = $questionInstallationManager->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);

        $this->assertPackagesInstall($packages, 'dev-master');
    }

    public function testInstallSkipPackageInstallIfPackageIsInRootPackage(): void
    {
        $jsonData = $this->getFixturesComposerJsonData();

        $this->localRepositoryMock->shouldReceive('getPackages')
            ->once()
            ->andReturn([]);

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

        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);

        $packages = $questionInstallationManager->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);

        self::assertCount(0, $packages);
    }

    public function testInstallWithPackageNameVersionAndDevStability(): void
    {
        $this->localRepositoryMock->shouldReceive('getPackages')
            ->once()
            ->andReturn([]);

        $rootPackageMock = $this->arrangeSimpleRootPackage();
        $this->arrangeDownloadAndWritePackagistData();

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(true);

        $this->arrangeInstallationManager();

        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);

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

        $questionInstallationManager->setInstaller($this->arrangeInstaller(['viserio/routing']));

        $composerPackage = $this->arrangeComposerPackage(['name' => 'viserio/routing', 'version' => $routingPackageVersion]);

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

        $jsonData = $this->getFixturesComposerJsonWithoutVersionData();
        $packages = $questionInstallationManager->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);

        $this->assertPackagesInstall($packages, $routingPackageVersion);
    }

    public function testUninstall(): void
    {
        $filesystemPackage = $this->arrangeComposerPackage(['name' => 'symfony/filesystem', 'version' => '^4.0']);
        $this->localRepositoryMock->shouldReceive('getPackages')
            ->twice()
            ->andReturn([$filesystemPackage]);

        $require = [
            'requires/test' => new Link(
                '__root__',
                'requires/test',
                (new VersionParser())->parseConstraints('dev-master'),
                'requires',
                'dev-master'
            ),
            'viserio/bus' => new Link(
                '__root__',
                'viserio/bus',
                (new VersionParser())->parseConstraints('dev-master'),
                'requires',
                'dev-master'
            ),
            'viserio/view' => new Link(
                '__root__',
                'viserio/view',
                (new VersionParser())->parseConstraints('dev-master'),
                'requires',
                'dev-master'
            ),
            'symfony/filesystem' => new Link(
                '__root__',
                'symfony/filesystem',
                (new VersionParser())->parseConstraints('^4.0'),
                'requires',
                '^4.0.0'
            ),
        ];

        $rootPackageMock = $this->setupRootPackage([], 'dev', $require, []);

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($rootPackageMock);

        $this->arrangeInstallationManager();

        $questionInstallationManager = $this->getQuestionInstallationManager($this->composerJsonWithRequiresPath);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Updating composer.json');

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Updating root package');

        $rootPackageMock->shouldReceive('setRequires')
            ->once()
            ->with([
                'viserio/view'       => $require['viserio/view'],
                'symfony/filesystem' => $require['symfony/filesystem'],
            ]);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Running an update to install dependent packages');

        $this->composerMock->shouldReceive('setPackage')
            ->once()
            ->with($rootPackageMock);

        $questionInstallationManager->setInstaller($this->arrangeInstaller(['requires/test' => 'dev-master', 'viserio/bus' => 'dev-master']));

        $operation1 = $this->mock(UninstallOperation::class);
        $operation1->shouldReceive('getPackage')
            ->once()
            ->andReturn($this->arrangeComposerPackage(['name' => 'requires/test', 'version' => 'dev-master']));
        $operation2 = $this->mock(UninstallOperation::class);
        $operation2->shouldReceive('getPackage')
            ->once()
            ->andReturn($this->arrangeComposerPackage(['name' => 'viserio/bus', 'version' => 'dev-master']));

        $installationManager = $this->mock(InstallationManager::class);
        $installationManager->shouldReceive('getOperations')
            ->once()
            ->andReturn([$operation1, $operation2]);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManager);

        $packages = $questionInstallationManager->uninstall('requires/test', ['requires/test' => 'dev-master', 'viserio/bus' => 'dev-master']);
        $jsonData = $this->getComposerJsonWithRequiresData();

        self::assertArrayHasKey('viserio/view', $jsonData['require']);
        self::assertCount(2, $packages);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    /**
     * @return array
     */
    private function getFixturesComposerJsonWithoutVersionData(): array
    {
        return \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/composer_without_version.json'), true);
    }

    private function createComposerJsonWithRequires(): void
    {
        $this->composerJsonWithRequiresPath = $this->composerCachePath . '/composer_with_requires.json';

        \file_put_contents(
            $this->composerJsonWithRequiresPath,
            '{
    "name": "requires/test",
    "authors": [
        {
            "name": "Daniel Bannert",
            "email": "d.bannert@anolilab.de"
        }
    ],
    "require": {
        "requires/test": "dev-master",
        "viserio/bus": "dev-master",
        "viserio/view": "dev-master",
        "symfony/filesystem": "^4.0"
    }
}'
        );
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
            ->andReturn($jsonData['extra'] ?? ['discovery' => []]);
        $composerPackage->shouldReceive('getName')
            ->andReturn($jsonData['name']);
        $composerPackage->shouldReceive('getRequires')
            ->andReturn($jsonData['require'] ?? []);
        $composerPackage->shouldReceive('getDevRequires')
            ->andReturn($jsonData['dev-require'] ?? []);
        $composerPackage->shouldReceive('getPrettyVersion')
            ->andReturn($jsonData['version']);
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
     * @param string $composerFilePath
     *
     * @return \Narrowspark\Discovery\Test\Fixtures\MockedQuestionInstallationManager
     */
    private function getQuestionInstallationManager(string $composerFilePath): MockedQuestionInstallationManager
    {
        $this->configMock->shouldReceive('get')
            ->once()
            ->with('vendor-dir')
            ->andReturn(__DIR__);
        $this->composerMock->shouldReceive('getConfig')
            ->andReturn($this->configMock);

        $manager = new MockedQuestionInstallationManager(
            $this->composerMock,
            $this->ioMock,
            $this->inputMock
        );

        $manager->setComposerFile($composerFilePath);

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
        $baseInstallationManager = $this->mock(BaseInstallationManager::class);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($baseInstallationManager);

        $this->composerMock->shouldReceive('setInstallationManager')
            ->once()
            ->with(\Mockery::type(InstallationManager::class));

        $this->composerMock->shouldReceive('setInstallationManager')
            ->once()
            ->with($baseInstallationManager);
    }

    /**
     * @return array
     */
    private function getFixturesComposerJsonData(): array
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

    /**
     * @return array
     */
    private function getComposerJsonWithRequiresData(): array
    {
        return \json_decode(\file_get_contents($this->composerJsonWithRequiresPath), true);
    }

    /**
     * @param array  $packages
     * @param string $version
     */
    private function assertPackagesInstall(array $packages, string $version): void
    {
        $jsonData = $this->getManipulatedComposerJsonData();

        self::assertSame(['viserio/routing' => $version], $jsonData['require']);
        self::assertCount(1, $packages);
    }

    private function createManipulatedComposer(): void
    {
        $this->manipulatedComposerPath = $this->composerCachePath . '/manipulated_composer.json';

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
    }

    /**
     * @param string $stability
     *
     * @return \Composer\Package\RootPackageInterface|\Mockery\MockInterface
     */
    private function arrangeSimpleRootPackage(string $stability = 'dev')
    {
        $rootPackageMock = $this->setupRootPackage([], $stability, [], []);

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($rootPackageMock);

        return $rootPackageMock;
    }
}
