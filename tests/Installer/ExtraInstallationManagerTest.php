<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Installer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use Narrowspark\Discovery\Installer\ExtraInstallationManager;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;

class ExtraInstallationManagerTest extends MockeryTestCase
{
    /**
     * @var \Composer\Composer|\Mockery\MockInterface
     */
    private $composerMock;

    /**
     * @var \Composer\IO\IOInterface|\Mockery\MockInterface
     */
    private $ioMock;

    /**
     * @var \Mockery\MockInterface|\Symfony\Component\Console\Input\InputInterface
     */
    private $inputMock;

    /**
     * @var \Narrowspark\Discovery\Installer\ExtraInstallationManager
     */
    private $installer;

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

        $rootPackageMock = $this->mock(RootPackageInterface::class);
        $rootPackageMock->shouldReceive('getExtra')
            ->andReturn([]);
        $rootPackageMock->shouldReceive('getMinimumStability')
            ->once()
            ->andReturn('stable');

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($rootPackageMock);

        $packageMock = $this->mock(Package::class);
        $packageMock->shouldReceive('getName')
            ->andReturn('foo/bar');
        $packageMock->shouldReceive('getPrettyVersion')
            ->andReturn('dev-master');

        $localRepositoryMock = $this->mock(WritableRepositoryInterface::class);
        $localRepositoryMock->shouldReceive('getPackages')
            ->once()
            ->andReturn([
                $packageMock,
            ]);

        $repositoryMock = $this->mock(RepositoryManager::class);
        $repositoryMock->shouldReceive('getLocalRepository')
            ->andReturn($localRepositoryMock);

        $this->composerMock->shouldReceive('getRepositoryManager')
            ->andReturn($repositoryMock);

        $this->installer = new ExtraInstallationManager(
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

        $packages = $this->installer->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);

        self::assertCount(0, $packages);
    }

//    public function testInstallOnEnabledInteractive(): void
//    {
//        $jsonData = \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/composer.json'), true);
//
//        $this->ioMock->shouldReceive('isInteractive')
//            ->once()
//            ->andReturn(true);
//
//        $packages = $this->installer->install($jsonData['name'], $jsonData['extra']['discovery']['extra-dependency']);
//
//        self::assertCount(2, $packages);
//    }
}
