<?php
//declare(strict_types=1);
//namespace Narrowspark\Automatic\Test\Installer;
//
//use Composer\DependencyResolver\Operation\InstallOperation;
//use Composer\DependencyResolver\Operation\UninstallOperation;
//use Composer\Installer;
//use Composer\Installer\InstallationManager as BaseInstallationManager;
//use Composer\IO\IOInterface;
//use Composer\Package\Link;
//use Composer\Package\PackageInterface;
//use Composer\Package\RootPackageInterface;
//use Composer\Repository\RepositoryManager;
//use Composer\Repository\WritableRepositoryInterface;
//use Composer\Semver\VersionParser;
//use Composer\Util\RemoteFilesystem;
//use Mockery\MockInterface;
//use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
//use Narrowspark\Automatic\Common\Contract\Exception\RuntimeException;
//use Narrowspark\Automatic\Common\Installer\InstallationManager;
//use Narrowspark\Automatic\Common\Package;
//use Narrowspark\Automatic\OperationsResolver;
//use Narrowspark\Automatic\Test\Fixtures\ComposerJsonFactory;
//use Narrowspark\Automatic\Test\Fixtures\MockedQuestionInstallationManager;
//use Narrowspark\Automatic\Test\Traits\ArrangeComposerClasses;
//use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
//use Symfony\Component\Filesystem\Filesystem;
//
///**
// * @internal
// */
//final class QuestionInstallationManagerTest extends MockeryTestCase
//{
//    use ArrangeComposerClasses;
//
//    /**
//     * @var string
//     */
//    private $manipulatedComposerPath;
//
//    /**
//     * @var string
//     */
//    private $composerJsonWithVersionPath;
//
//    /**
//     * @var string
//     */
//    private $composerJsonWithoutVersionPath;
//
//    /**
//     * @var string
//     */
//    private $composerJsonWithRequiresPath;
//
//    /**
//     * @var \Composer\Repository\WritableRepositoryInterface|\Mockery\MockInterface
//     */
//    private $localRepositoryMock;
//
//    /**
//     * {@inheritdoc}
//     */
//    protected function setUp(): void
//    {
//        $this->composerCachePath = __DIR__ . '/QuestionInstallationManagerTest';
//
//        $this->manipulatedComposerPath        = $this->composerCachePath . '/manipulated_composer.json';
//        $this->composerJsonWithRequiresPath   = $this->composerCachePath . '/composer_with_requires.json';
//        $this->composerJsonWithVersionPath    = $this->composerCachePath . '/composer_with_version.json';
//        $this->composerJsonWithoutVersionPath = $this->composerCachePath . '/composer_without_version.json';
//
//        @\mkdir($this->composerCachePath);
//        \putenv('COMPOSER_CACHE_DIR=' . $this->composerCachePath);
//
//        parent::setUp();
//
//        $this->arrangeComposerClasses();
//
//        if (! \method_exists(RemoteFilesystem::class, 'getRemoteContents')) {
//            $this->ioMock->shouldReceive('writeError')
//                ->once()
//                ->with('Writing ' . $this->composerCachePath . '/repo/https---packagist.org/packages.json into cache', true, IOInterface::DEBUG);
//        } else {
//            $this->ioMock->shouldReceive('writeError')
//                ->with('Downloading https://repo.packagist.org/packages.json', true, IOInterface::DEBUG);
//            $this->ioMock->shouldReceive('writeError')
//                ->once()
//                ->with('Writing ' . $this->composerCachePath . '/repo/https---repo.packagist.org/packages.json into cache', true, IOInterface::DEBUG);
//        }
//
//        $this->createComposerJsonFiles();
//
//        $this->arrangePackagist();
//
//        $this->localRepositoryMock = $this->mock(WritableRepositoryInterface::class);
//
//        $repositoryMock = $this->mock(RepositoryManager::class);
//        $repositoryMock->shouldReceive('getLocalRepository')
//            ->once()
//            ->andReturn($this->localRepositoryMock);
//
//        $this->composerMock->shouldReceive('getRepositoryManager')
//            ->once()
//            ->andReturn($repositoryMock);
//
//        $this->composerMock->shouldReceive('getConfig')
//            ->andReturn($this->configMock);
//    }
//
//    /**
//     * {@inheritdoc}
//     */
//    protected function tearDown(): void
//    {
//        parent::tearDown();
//
//        \putenv('COMPOSER_CACHE_DIR=');
//        \putenv('COMPOSER_CACHE_DIR');
//
//        (new Filesystem())->remove($this->composerCachePath);
//    }
//
//    public function testInstallOnDisabledInteractive(): void
//    {
//        $this->arrangeEmptyLocalRepositoryPackages();
//
//        $this->ioMock->shouldReceive('isInteractive')
//            ->once()
//            ->andReturn(false);
//
//        $this->arrangeSimpleRootPackage('stable');
//
//        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);
//
//        $jsonData = ComposerJsonFactory::jsonFileToArray($this->composerJsonWithVersionPath);
//
//        $packages = $questionInstallationManager->install(
//            $this->arrangeInstallPackage($jsonData['name']),
//            $jsonData['extra']['automatic']['extra-dependency']
//        );
//
//        static::assertCount(0, $packages);
//    }
//
//    public function testInstallWithEmptyDependencies(): void
//    {
//        $jsonData = ComposerJsonFactory::jsonFileToArray($this->composerJsonWithVersionPath);
//
//        $this->arrangeEmptyLocalRepositoryPackages();
//
//        $this->arrangeActiveIsInteractive();
//
//        $this->arrangeSimpleRootPackage('stable');
//
//        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);
//
//        $packages = $questionInstallationManager->install(
//            $this->arrangeInstallPackage($jsonData['name']),
//            []
//        );
//
//        static::assertCount(0, $packages);
//    }
//
//    public function testInstallWithAEmptyQuestion(): void
//    {
//        $this->expectException(RuntimeException::class);
//        $this->expectExceptionMessage('You must provide at least two optional dependencies.');
//
//        $jsonData = ComposerJsonFactory::jsonFileToArray($this->composerJsonWithVersionPath);
//
//        $this->arrangeEmptyLocalRepositoryPackages();
//
//        $this->arrangeActiveIsInteractive();
//
//        $this->composerMock->shouldReceive('getInstallationManager')
//            ->once()
//            ->andReturn($this->mock(BaseInstallationManager::class));
//
//        $this->composerMock->shouldReceive('setInstallationManager')
//            ->once();
//
//        $this->arrangeSimpleRootPackage('stable');
//
//        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);
//
//        $questionInstallationManager->install(
//            $this->arrangeInstallPackage($jsonData['name']),
//            ['this is a question' => []]
//        );
//    }
//
//    public function testInstallThrowsExceptionWhenNoVersionIsFound(): void
//    {
//        $this->expectException(InvalidArgumentException::class);
//        $this->expectExceptionMessage('Could not find package viserio/routing at any version for your minimum-stability (stable). Check the package spelling or your minimum-stability.');
//
//        $jsonData = ComposerJsonFactory::jsonFileToArray($this->composerJsonWithoutVersionPath);
//
//        $this->arrangeEmptyLocalRepositoryPackages();
//        $this->arrangeDownloadAndWritePackagistData();
//        $this->arrangeSimpleRootPackage('stable');
//        $this->arrangeActiveIsInteractive();
//
//        $this->composerMock->shouldReceive('setInstallationManager')
//            ->once();
//
//        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);
//
//        $installationManager = $this->mock(InstallationManager::class);
//        $this->composerMock->shouldReceive('getInstallationManager')
//            ->once()
//            ->andReturn($installationManager);
//
//        $questionInstallationManager->install(
//            $this->arrangeInstallPackage($jsonData['name']),
//            $jsonData['extra']['automatic']['extra-dependency']
//        );
//    }
//
//    public function testInstallWithPackageNameAndVersionWithStablePackageVersions(): void
//    {
//        $jsonData = ComposerJsonFactory::jsonFileToArray($this->composerJsonWithVersionPath);
//
//        $this->arrangeEmptyLocalRepositoryPackages();
//        $this->arrangeActiveIsInteractive();
//        $this->arrangeInstallationManager();
//
//        $this->ioMock->shouldReceive('askAndValidate')
//            ->once()
//            ->with(
//                '<question>this is a question</question>
//  [<comment>0</comment>] viserio/routing : dev-master
//  [<comment>1</comment>] viserio/view : dev-master
//  Make your selection: ',
//                \Mockery::type(\Closure::class)
//            )
//            ->andReturn('viserio/routing');
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Using version <info>dev-master</info> for <info>viserio/routing</info>');
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Updating composer.json');
//
//        $this->arrangeConfigSortPackages();
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Updating root package');
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Running an update to install dependent packages');
//
//        $rootPackageMock = $this->arrangeSimpleRootPackage('stable');
//        $rootPackageMock->shouldReceive('setRequires')
//            ->once()
//            ->with([
//                'viserio/routing' => new Link(
//                    '__root__',
//                    'viserio/routing',
//                    (new VersionParser())->parseConstraints('dev-master'),
//                    'relates to',
//                    'dev-master'
//                ),
//            ]);
//        $rootPackageMock->shouldReceive('setDevRequires')
//            ->once()
//            ->with([]);
//
//        $this->composerMock->shouldReceive('setPackage')
//            ->once()
//            ->with($rootPackageMock);
//
//        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);
//
//        $questionInstallationManager->setInstaller($this->arrangeInstaller(['viserio/routing']));
//
//        $composerPackage = $this->arrangeComposerPackage(['name' => 'prisis/test', 'version' => 'dev-master']);
//
//        $operation = $this->arrangeInstallOperation($composerPackage);
//
//        $installationManager = $this->mock(InstallationManager::class);
//        $installationManager->shouldReceive('getOperations')
//            ->once()
//            ->andReturn([$operation]);
//
//        $this->composerMock->shouldReceive('getInstallationManager')
//            ->once()
//            ->andReturn($installationManager);
//
//        $packages = $questionInstallationManager->install(
//            $this->arrangeInstallPackage($jsonData['name']),
//            $jsonData['extra']['automatic']['extra-dependency']
//        );
//
//        $this->assertPackagesInstall($packages, 'dev-master');
//        static::assertCount(1, $questionInstallationManager->getPackagesToInstall());
//    }
//
//    public function testInstallSkipPackageInstallIfPackageIsInRootPackage(): void
//    {
//        $jsonData = ComposerJsonFactory::jsonFileToArray($this->composerJsonWithVersionPath);
//
//        $this->arrangeEmptyLocalRepositoryPackages();
//
//        $requires = [
//            'viserio/routing' => new Link(
//                '__root__',
//                'viserio/routing',
//                (new VersionParser())->parseConstraints('dev-master'),
//                'relates to',
//                'dev-master'
//            ),
//        ];
//
//        $rootPackageMock = $this->setupRootPackage([], 'stable', $requires, []);
//
//        $this->composerMock->shouldReceive('getPackage')
//            ->once()
//            ->andReturn($rootPackageMock);
//
//        $this->arrangeActiveIsInteractive();
//        $this->arrangeInstallationManager();
//
//        $this->ioMock->shouldReceive('askAndValidate')
//            ->never()
//            ->with(
//                '<question>this is a question</question>
//  [<comment>0</comment>] viserio/routing : dev-master
//  [<comment>1</comment>] viserio/view : dev-master
//  Make your selection: ',
//                \Mockery::type(\Closure::class)
//            )
//            ->andReturn('viserio/routing');
//
//        $this->ioMock->shouldReceive('writeError')
//            ->never()
//            ->with('Using version <info>dev-master</info> for <info>viserio/routing</info>');
//
//        $this->ioMock->shouldReceive('writeError')
//            ->never()
//            ->with('Updating composer.json');
//
//        $this->ioMock->shouldReceive('writeError')
//            ->never()
//            ->with('Updating root package');
//
//        $rootPackageMock->shouldReceive('setRequires')
//            ->never()
//            ->with($requires);
//
//        $this->ioMock->shouldReceive('writeError')
//            ->never()
//            ->with('Running an update to install dependent packages');
//
//        $this->composerMock->shouldReceive('setPackage')
//            ->never()
//            ->with($rootPackageMock);
//
//        $installationManager = $this->mock(InstallationManager::class);
//        $installationManager->shouldReceive('getOperations')
//            ->once()
//            ->andReturn([]);
//
//        $this->composerMock->shouldReceive('getInstallationManager')
//            ->once()
//            ->andReturn($installationManager);
//
//        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);
//
//        $packages = $questionInstallationManager->install(
//            $this->arrangeInstallPackage($jsonData['name']),
//            $jsonData['extra']['automatic']['extra-dependency']
//        );
//
//        static::assertCount(0, $packages);
//        static::assertCount(0, $questionInstallationManager->getPackagesToInstall());
//    }
//
//    public function testInstallWithPackageNameVersionAndDevStability(): void
//    {
//        $this->arrangeEmptyLocalRepositoryPackages();
//
//        $rootPackageMock = $this->arrangeSimpleRootPackage();
//
//        $this->arrangeDownloadAndWritePackagistData();
//        $this->arrangeActiveIsInteractive();
//        $this->arrangeInstallationManager();
//
//        $questionInstallationManager = $this->getQuestionInstallationManager($this->manipulatedComposerPath);
//
//        $versionSelector       = $questionInstallationManager->getVersionSelector();
//        $packageName           = 'viserio/routing';
//        $routingPackageVersion = $versionSelector->findRecommendedRequireVersion($versionSelector->findBestCandidate($packageName));
//        $viewPackageVersion    = $versionSelector->findRecommendedRequireVersion($versionSelector->findBestCandidate('viserio/view'));
//
//        $this->ioMock->shouldReceive('askAndValidate')
//            ->once()
//            ->with(
//                '<question>this is a question</question>
//  [<comment>0</comment>] viserio/routing : ' . $routingPackageVersion . '
//  [<comment>1</comment>] viserio/view : ' . $viewPackageVersion . '
//  Make your selection: ',
//                \Mockery::type(\Closure::class)
//            )
//            ->andReturn($packageName);
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Using version <info>' . $routingPackageVersion . '</info> for <info>viserio/routing</info>');
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Updating composer.json');
//
//        $this->arrangeConfigSortPackages();
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Updating root package');
//
//        $rootPackageMock->shouldReceive('setRequires')
//            ->once()
//            ->with([
//                'viserio/routing' => new Link(
//                    '__root__',
//                    'viserio/routing',
//                    (new VersionParser())->parseConstraints($routingPackageVersion),
//                    'relates to',
//                    $routingPackageVersion
//                ),
//            ]);
//        $rootPackageMock->shouldReceive('setDevRequires')
//            ->once()
//            ->with([]);
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Running an update to install dependent packages');
//
//        $this->composerMock->shouldReceive('setPackage')
//            ->once()
//            ->with($rootPackageMock);
//
//        $questionInstallationManager->setInstaller($this->arrangeInstaller(['viserio/routing']));
//
//        $composerPackage = $this->arrangeComposerPackage(['name' => 'viserio/routing', 'version' => $routingPackageVersion]);
//
//        $operation = $this->arrangeInstallOperation($composerPackage);
//
//        $installationManager = $this->mock(InstallationManager::class);
//        $installationManager->shouldReceive('getOperations')
//            ->once()
//            ->andReturn([$operation]);
//
//        $this->composerMock->shouldReceive('getInstallationManager')
//            ->once()
//            ->andReturn($installationManager);
//
//        $jsonData = ComposerJsonFactory::jsonFileToArray($this->composerJsonWithoutVersionPath);
//
//        $packages = $questionInstallationManager->install(
//            $this->arrangeInstallPackage($jsonData['name']),
//            $jsonData['extra']['automatic']['extra-dependency']
//        );
//
//        $this->assertPackagesInstall($packages, $routingPackageVersion);
//    }
//
//    public function testInstallCanAddQuestionPackageToRootComposerJson(): void
//    {
//        $this->localRepositoryMock->shouldReceive('getPackages')
//            ->once()
//            ->andReturn([
//                $this->arrangeComposerPackage(['name' => 'symfony/filesystem', 'version' => '4.0']),
//            ]);
//
//        $this->arrangeActiveIsInteractive();
//        $this->arrangeInstallationManager();
//
//        $require = [
//            'viserio/bus' => new Link(
//                '__root__',
//                'viserio/bus',
//                (new VersionParser())->parseConstraints('dev-master'),
//                'relates to',
//                'dev-master'
//            ),
//            'viserio/view' => new Link(
//                '__root__',
//                'viserio/view',
//                (new VersionParser())->parseConstraints('dev-master'),
//                'relates to',
//                'dev-master'
//            ),
//        ];
//
//        $rootPackageMock = $this->setupRootPackage([], 'dev', $require, []);
//
//        $this->composerMock->shouldReceive('getPackage')
//            ->once()
//            ->andReturn($rootPackageMock);
//
//        $questionInstallationManager = $this->getQuestionInstallationManager($this->composerJsonWithRequiresPath);
//
//        $package = new Package(
//            'requirestest',
//            'requires/test',
//            __DIR__,
//            false,
//            [
//                'version'             => 'dev-master',
//                'url'                 => null,
//                'operation'           => 'install',
//                'type'                => 'library',
//                'extraDependencyOf'   => null,
//                'require'             => [
//                    'viserio/view' => 'dev-master',
//                ],
//                'extra-dependency' => [
//                    'this is a question' => [
//                        'viserio/routing',
//                        'viserio/support',
//                        'symfony/filesystem',
//                    ],
//                ],
//                'selected-question-packages' => [
//                    'symfony/filesystem' => '^4.0.8',
//                ],
//            ]
//        );
//
//        $this->ioMock->shouldReceive('write')
//            ->once()
//            ->with('Added package <info>symfony/filesystem</info> to composer.json with constraint <info>^4.0</info>; to upgrade, run <info>composer require symfony/filesystem:VERSION</info>');
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Updating composer.json');
//
//        $this->arrangeConfigSortPackages();
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Updating root package');
//
//        $rootPackageMock->shouldReceive('setRequires')
//            ->once()
//            ->with([
//                'viserio/bus'        => $require['viserio/bus'],
//                'viserio/view'       => $require['viserio/view'],
//                'symfony/filesystem' => new Link(
//                    '__root__',
//                    'symfony/filesystem',
//                    (new VersionParser())->parseConstraints('^4.0'),
//                    'relates to',
//                    '^4.0'
//                ),
//            ]);
//        $rootPackageMock->shouldReceive('setDevRequires')
//            ->once()
//            ->with([]);
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Running an update to install dependent packages');
//
//        $this->composerMock->shouldReceive('setPackage')
//            ->once()
//            ->with($rootPackageMock);
//
//        $operation = $this->arrangeInstallOperation($this->arrangeComposerPackage(['name' => 'symfony/filesystem', 'version' => '^4.0']));
//
//        $installationManager = $this->mock(InstallationManager::class);
//        $installationManager->shouldReceive('getOperations')
//            ->once()
//            ->andReturn([$operation]);
//
//        $this->composerMock->shouldReceive('getInstallationManager')
//            ->once()
//            ->andReturn($installationManager);
//
//        $questionInstallationManager->setInstaller($this->arrangeInstaller(['symfony/filesystem']));
//
//        $packages = $questionInstallationManager->install(
//            $package,
//            $package->getConfiguratorOptions('extra-dependency')
//        );
//
//        static::assertCount(1, $packages);
//    }
//
//    public function testUninstall(): void
//    {
//        $this->localRepositoryMock->shouldReceive('getPackages')
//            ->twice()
//            ->andReturn([
//                $this->arrangeComposerPackage(['name' => 'symfony/filesystem', 'version' => '^4.0']),
//            ]);
//
//        $require = [
//            'viserio/bus' => new Link(
//                '__root__',
//                'viserio/bus',
//                (new VersionParser())->parseConstraints('dev-master'),
//                'relates to',
//                'dev-master'
//            ),
//            'viserio/view' => new Link(
//                '__root__',
//                'viserio/view',
//                (new VersionParser())->parseConstraints('dev-master'),
//                'relates to',
//                'dev-master'
//            ),
//        ];
//
//        $rootPackageMock = $this->setupRootPackage([], 'dev', $require, []);
//
//        $this->composerMock->shouldReceive('getPackage')
//            ->once()
//            ->andReturn($rootPackageMock);
//
//        $this->arrangeInstallationManager();
//
//        $questionInstallationManager = $this->getQuestionInstallationManager($this->composerJsonWithRequiresPath);
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Updating composer.json');
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Updating root package');
//
//        $rootPackageMock->shouldReceive('setRequires')
//            ->once()
//            ->with([
//                'viserio/bus' => $require['viserio/bus'],
//            ]);
//        $rootPackageMock->shouldReceive('setDevRequires')
//            ->once()
//            ->with([]);
//
//        $this->ioMock->shouldReceive('writeError')
//            ->once()
//            ->with('Running an update to install dependent packages');
//
//        $this->composerMock->shouldReceive('setPackage')
//            ->once()
//            ->with($rootPackageMock);
//
//        $questionInstallationManager->setInstaller($this->arrangeInstaller(['viserio/view' => 'dev-master']));
//
//        $operation1 = $this->mock(UninstallOperation::class);
//        $operation1->shouldReceive('getPackage')
//            ->once()
//            ->andReturn($this->arrangeComposerPackage(['name' => 'requires/test', 'version' => 'dev-master']));
//        $operation2 = $this->mock(UninstallOperation::class);
//        $operation2->shouldReceive('getPackage')
//            ->once()
//            ->andReturn($this->arrangeComposerPackage(['name' => 'viserio/bus', 'version' => 'dev-master']));
//
//        $installationManager = $this->mock(InstallationManager::class);
//        $installationManager->shouldReceive('getOperations')
//            ->once()
//            ->andReturn([$operation1, $operation2]);
//
//        $this->composerMock->shouldReceive('getInstallationManager')
//            ->once()
//            ->andReturn($installationManager);
//
//        $packageOptions = [
//            'version'             => 'dev-master',
//            'url'                 => null,
//            'operation'           => 'install',
//            'type'                => 'library',
//            'extraDependencyOf'   => null,
//            'require'             => [
//                'viserio/view' => 'dev-master',
//            ],
//            'extra-dependency' => [
//                'this is a question' => [
//                    'viserio/routing',
//                    'viserio/support',
//                    'symfony/filesystem',
//                ],
//            ],
//            'selected-question-packages' => [
//                'symfony/filesystem' => '^4.0.8',
//            ],
//        ];
//
//        $this->lockMock->shouldReceive('has')
//            ->once()
//            ->with('requires/test')
//            ->andReturn(true);
//        $this->lockMock->shouldReceive('has')
//            ->once()
//            ->with('viserio/bus')
//            ->andReturn(false);
//        $this->lockMock->shouldReceive('get')
//            ->once()
//            ->with('requires/test')
//            ->andReturn($packageOptions);
//
//        $package = new Package(
//            'requirestest',
//            'requires/test',
//            __DIR__,
//            false,
//            $packageOptions
//        );
//
//        $packages = $questionInstallationManager->uninstall($package, ['viserio/view' => 'dev-master']);
//        $jsonData = ComposerJsonFactory::jsonFileToArray($this->composerJsonWithRequiresPath);
//
//        static::assertArrayHasKey('viserio/bus', $jsonData['require']);
//        static::assertCount(2, $packages);
//    }
//
//    /**
//     * {@inheritdoc}
//     */
//    protected function allowMockingNonExistentMethods($allow = false): void
//    {
//        parent::allowMockingNonExistentMethods(true);
//    }
//
//    protected function arrangeConfigSortPackages(): void
//    {
//        $this->configMock->shouldReceive('get')
//            ->once()
//            ->with('sort-packages')
//            ->andReturn(true);
//    }
//
//    /**
//     * @param array $with
//     * @param int   $run
//     *
//     * @return \Composer\Installer|\Mockery\MockInterface
//     */
//    private function arrangeInstaller(array $with, int $run = 0): MockInterface
//    {
//        $installer = $this->mock(Installer::class);
//        $installer->shouldReceive('setUpdateWhitelist')
//            ->once()
//            ->with($with);
//        $installer->shouldReceive('run')
//            ->once()
//            ->andReturn($run);
//
//        return $installer;
//    }
//
//    /**
//     * @param array $jsonData
//     *
//     * @return \Composer\Package\PackageInterface|\Mockery\MockInterface
//     */
//    private function arrangeComposerPackage(array $jsonData): MockInterface
//    {
//        $composerPackage = $this->mock(PackageInterface::class);
//        $composerPackage->shouldReceive('getExtra')
//            ->andReturn($jsonData['extra'] ?? ['automatic' => []]);
//        $composerPackage->shouldReceive('getName')
//            ->andReturn($jsonData['name']);
//        $composerPackage->shouldReceive('getRequires')
//            ->andReturn($jsonData['require'] ?? []);
//        $composerPackage->shouldReceive('getDevRequires')
//            ->andReturn($jsonData['dev-require'] ?? []);
//        $composerPackage->shouldReceive('getPrettyVersion')
//            ->andReturn($jsonData['version']);
//        $composerPackage->shouldReceive('getSourceUrl')
//            ->andReturn(null);
//        $composerPackage->shouldReceive('getType')
//            ->andReturn(null);
//
//        return $composerPackage;
//    }
//
//    /**
//     * @param array       $extra
//     * @param null|string $stability
//     * @param array       $requires
//     * @param array       $devRequires
//     *
//     * @return \Composer\Package\RootPackageInterface|\Mockery\MockInterface
//     */
//    private function setupRootPackage(array $extra, ?string $stability, array $requires, array $devRequires): MockInterface
//    {
//        $rootPackageMock = $this->mock(RootPackageInterface::class);
//
//        $rootPackageMock->shouldReceive('getExtra')
//            ->andReturn($extra);
//        $rootPackageMock->shouldReceive('getMinimumStability')
//            ->andReturn($stability);
//        $rootPackageMock->shouldReceive('getRequires')
//            ->andReturn($requires);
//        $rootPackageMock->shouldReceive('getDevRequires')
//            ->andReturn($devRequires);
//
//        return $rootPackageMock;
//    }
//
//    /**
//     * @param string $composerFilePath
//     *
//     * @return \Narrowspark\Automatic\Test\Fixtures\MockedQuestionInstallationManager
//     */
//    private function getQuestionInstallationManager(string $composerFilePath): MockedQuestionInstallationManager
//    {
//        $manager = new MockedQuestionInstallationManager(
//            $this->composerMock,
//            $this->ioMock,
//            $this->inputMock,
//            new OperationsResolver($this->lockMock, __DIR__)
//        );
//
//        $manager->setComposerFile($composerFilePath);
//
//        return $manager;
//    }
//
//    private function arrangeDownloadAndWritePackagistData(): void
//    {
//        $this->ioMock->shouldReceive('writeError')
//            ->with(
//                \Mockery::on(function ($argument) {
//                    return \mb_strpos($argument, 'Downloading') !== false;
//                }),
//                true,
//                IOInterface::DEBUG
//            );
//        $this->ioMock->shouldReceive('writeError')
//            ->with(
//                \Mockery::on(function ($argument) {
//                    return \mb_strpos($argument, 'Writing') !== false;
//                }),
//                true,
//                IOInterface::DEBUG
//            );
//    }
//
//    private function arrangeInstallationManager(): void
//    {
//        $baseInstallationManager = $this->mock(BaseInstallationManager::class);
//
//        $this->composerMock->shouldReceive('getInstallationManager')
//            ->once()
//            ->andReturn($baseInstallationManager);
//
//        $this->composerMock->shouldReceive('setInstallationManager')
//            ->once()
//            ->with(\Mockery::type(InstallationManager::class));
//
//        $this->composerMock->shouldReceive('setInstallationManager')
//            ->once()
//            ->with($baseInstallationManager);
//    }
//
//    /**
//     * @param array  $packages
//     * @param string $version
//     */
//    private function assertPackagesInstall(array $packages, string $version): void
//    {
//        $jsonData = $jsonData = ComposerJsonFactory::jsonFileToArray($this->manipulatedComposerPath);
//
//        static::assertSame(['viserio/routing' => $version], $jsonData['require']);
//        static::assertCount(1, $packages);
//    }
//
//    /**
//     * @param string $stability
//     *
//     * @return \Composer\Package\RootPackageInterface|\Mockery\MockInterface
//     */
//    private function arrangeSimpleRootPackage(string $stability = 'dev')
//    {
//        $rootPackageMock = $this->setupRootPackage([], $stability, [], []);
//
//        $this->composerMock->shouldReceive('getPackage')
//            ->once()
//            ->andReturn($rootPackageMock);
//
//        return $rootPackageMock;
//    }
//
//    /**
//     * @param string $name
//     *
//     * @return \Narrowspark\Automatic\Common\Package
//     */
//    private function arrangeInstallPackage(string $name): Package
//    {
//        return new Package(
//            $name,
//            $name,
//            __DIR__,
//            false,
//            [
//                'version'   => 'dev-master',
//                'url'       => null,
//                'operation' => 'install',
//                'type'      => 'library',
//            ]
//        );
//    }
//
//    /**
//     * @param \Composer\Package\PackageInterface|\Mockery\MockInterface $composerPackage
//     *
//     * @return \Composer\DependencyResolver\Operation\InstallOperation|\Mockery\MockInterface
//     */
//    private function arrangeInstallOperation($composerPackage): MockInterface
//    {
//        $operation = $this->mock(InstallOperation::class);
//        $operation->shouldReceive('getPackage')
//            ->once()
//            ->andReturn($composerPackage);
//
//        return $operation;
//    }
//
//    private function arrangeEmptyLocalRepositoryPackages(): void
//    {
//        $this->localRepositoryMock->shouldReceive('getPackages')
//            ->once()
//            ->andReturn([]);
//    }
//
//    private function arrangeActiveIsInteractive(): void
//    {
//        $this->ioMock->shouldReceive('isInteractive')
//            ->once()
//            ->andReturn(true);
//    }
//
//    private function createComposerJsonFiles(): void
//    {
//        \file_put_contents(
//            $this->manipulatedComposerPath,
//            ComposerJsonFactory::createComposerJson('manipulated/test')
//        );
//
//        \file_put_contents(
//            $this->composerJsonWithRequiresPath,
//            ComposerJsonFactory::createComposerJson(
//                'requires/test',
//                [
//                    'viserio/bus'  => 'dev-master',
//                    'viserio/view' => 'dev-master',
//                ]
//            )
//        );
//
//        \file_put_contents(
//            $this->composerJsonWithVersionPath,
//            ComposerJsonFactory::createAutomaticComposerJson(
//                'prisis/test',
//                [],
//                [],
//                [
//                    'extra-dependency' => [
//                        'this is a question' => [
//                            'viserio/routing' => 'dev-master',
//                            'viserio/view'    => 'dev-master',
//                        ],
//                    ],
//                ]
//            )
//        );
//
//        \file_put_contents(
//            $this->composerJsonWithoutVersionPath,
//            ComposerJsonFactory::createAutomaticComposerJson(
//                'prisis/test',
//                [],
//                [],
//                [
//                    'extra-dependency' => [
//                        'this is a question' => [
//                            'viserio/routing',
//                            'viserio/view',
//                        ],
//                    ],
//                ]
//            )
//        );
//    }
//}
