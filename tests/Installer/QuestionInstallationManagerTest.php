<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Installer;

use Composer\Composer;
use Composer\Config;
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
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;

class QuestionInstallationManagerTest extends MockeryTestCase
{
    /**
     * @var \Composer\Config|\Mockery\MockInterface
     */
    protected $configMock;

    /**
     * @var \Composer\Composer|\Mockery\MockInterface
     */
    protected $composerMock;
    /**
     * @var \Composer\IO\IOInterface|\Mockery\MockInterface
     */
    private $ioMock;

    /**
     * @var \Mockery\MockInterface|\Symfony\Component\Console\Input\InputInterface
     */
    private $inputMock;

    /**
     * @var \Composer\Package\RootPackageInterface|\Mockery\MockInterface
     */
    private $rootPackageMock;

    /**
     * @var \Narrowspark\Discovery\Installer\QuestionInstallationManager
     */
    private $questionInstallationManager;

    /**
     * @var string
     */
    private $composerCachePath;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composerCachePath = __DIR__ . '/cache';

        \mkdir($this->composerCachePath);

        \putenv('COMPOSER_CACHE_DIR=' . $this->composerCachePath);

        $this->composerMock = $this->mock(Composer::class);
        $this->ioMock       = $this->mock(IOInterface::class);
        $this->inputMock    = $this->mock(InputInterface::class);

        $this->ioMock->shouldReceive('hasAuthentication')
            ->once()
            ->andReturn(false);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Downloading https://packagist.org/packages.json', true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Writing ' . $this->composerCachePath . '/repo/https---packagist.org/packages.json into cache', true, IOInterface::DEBUG);

        $this->rootPackageMock = $this->mock(RootPackageInterface::class);
        $this->rootPackageMock->shouldReceive('getExtra')
            ->andReturn([]);
        $this->rootPackageMock->shouldReceive('getMinimumStability')
            ->once()
            ->andReturn('stable');

        $this->composerMock->shouldReceive('getPackage')
            ->andReturn($this->rootPackageMock);

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

        $this->configMock = $this->mock(Config::class);

        $this->questionInstallationManager = new MockedQuestionInstallationManager(
            $this->composerMock,
            $this->ioMock,
            $this->inputMock,
            __DIR__
        );
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

        $packages = $this->questionInstallationManager->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);

        self::assertCount(0, $packages);
    }

    public function testInstallOnEnabledInteractiveAndWithKeyValueAnswer(): void
    {
        $jsonData = \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/composer.json'), true);

        $this->ioMock->shouldReceive('isInteractive')
            ->once()
            ->andReturn(true);

        $this->composerMock->shouldReceive('getConfig')
            ->once()
            ->andReturn($this->configMock);
        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($this->mock(BaseInstallationManager::class));

        $this->composerMock->shouldReceive('setInstallationManager')
            ->twice();

        $this->rootPackageMock->shouldReceive('getRequires')
            ->twice()
            ->andReturn([]);
        $this->rootPackageMock->shouldReceive('getDevRequires')
            ->once()
            ->andReturn([]);

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

        $this->rootPackageMock->shouldReceive('setRequires')
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
            ->twice()
            ->with($this->rootPackageMock);

        $this->questionInstallationManager->setInstaller($this->arrangeInstaller(['viserio/routing']));

        $composerPackage = $this->mock(PackageInterface::class);
        $composerPackage->shouldReceive('getExtra')
            ->andReturn($jsonData['extra']);
        $composerPackage->shouldReceive('getName')
            ->andReturn('prisis/test');
        $composerPackage->shouldReceive('getRequires')
            ->andReturn($jsonData['require']);
        $composerPackage->shouldReceive('getPrettyVersion')
            ->andReturn('dev-master');
        $composerPackage->shouldReceive('getSourceUrl')
            ->andReturn(null);
        $composerPackage->shouldReceive('getType')
            ->andReturn(null);

        $operation = $this->mock(InstallOperation::class);
        $operation->shouldReceive('getPackage')
            ->once()
            ->andReturn($composerPackage);

        $installationManager = $this->mock(InstallationManager::class);
        $installationManager->shouldReceive('getOperations')
            ->andReturn([$operation]);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManager);

        $packages = $this->questionInstallationManager->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);

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
     *
     * @return \Composer\Installer|\Mockery\MockInterface
     */
    private function arrangeInstaller(array $with): MockInterface
    {
        $installer = $this->mock(Installer::class);
        $installer->shouldReceive('setUpdateWhitelist')
            ->once()
            ->with($with);
        $installer->shouldReceive('run')
            ->once()
            ->andReturn(0);

        return $installer;
    }
}
