<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Installer;

use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Installer\InstallationManager;
use Narrowspark\Automatic\Test\Traits\ArrangeComposerClasses;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Assert;

/**
 * @internal
 */
final class InstallationManagerTest extends MockeryTestCase
{
    use ArrangeComposerClasses;

    /**
     * @var \Composer\Package\Package|\Mockery\MockInterface
     */
    private $rootPackageMock;

    /**
     * @var \Composer\Repository\RepositoryInterface|\Mockery\MockInterface
     */
    private $localRepositoryMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composerCachePath = __DIR__ . '/AutomaticTest';

        @\mkdir($this->composerCachePath);
        \putenv('COMPOSER_CACHE_DIR=' . $this->composerCachePath);

        $this->arrangeComposerClasses();

        $this->rootPackageMock = $this->mock(RootPackageInterface::class);
        $this->rootPackageMock->shouldReceive('getMinimumStability')
            ->andReturn(null);

        $this->localRepositoryMock = $this->mock(RepositoryInterface::class);

        $repositoryMock = $this->mock(RepositoryManager::class);
        $repositoryMock->shouldReceive('getLocalRepository')
            ->once()
            ->andReturn($this->localRepositoryMock);

        $this->composerMock->shouldReceive('getRepositoryManager')
            ->andReturn($repositoryMock);

        $this->composerMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($this->rootPackageMock);

        $this->ioMock->shouldReceive('loadConfiguration');

        $this->arrangePackagist();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        \putenv('COMPOSER_CACHE_DIR=');
        \putenv('COMPOSER_CACHE_DIR');

        $narrowsparkPath = __DIR__ . \DIRECTORY_SEPARATOR . 'narrowspark';

        $this->delete($this->composerCachePath);
        $this->delete($narrowsparkPath);

        @\unlink($this->composerCachePath . \DIRECTORY_SEPARATOR . '.htaccess');
        @\rmdir($this->composerCachePath);
        @\rmdir($narrowsparkPath);
    }

    public function testInstallWithoutRequireAndRequireDev(): void
    {
        $this->ioMock->shouldReceive('writeError')
            ->atLeast()
            ->once();

        $this->rootPackageMock->shouldReceive('getRequires')
            ->once()
            ->andReturn([]);

        $this->rootPackageMock->shouldReceive('getDevRequires')
            ->once()
            ->andReturn([]);

        $manager = new InstallationManager($this->composerMock, $this->ioMock, $this->inputMock);

        $manager->install([]);
    }

    public function testInstallWithRequireAndNoPackageVersion(): void
    {
        $path    = __DIR__ . '/Fixture/composer.json';
        $dirPath = \dirname($path);

        @\mkdir($dirPath);
        @\file_put_contents($path, \json_encode(['require' => [], 'require-dev' => []]));
        \putenv('COMPOSER=' . $path);

        $this->ioMock->shouldReceive('writeError')
            ->with('Downloading https://repo.packagist.org/packages.json', true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('writeError')
            ->with(\Mockery::type('string'), true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('writeError')
            ->with('Updating composer.json');
        $this->ioMock->shouldReceive('writeError')
            ->with('Updating root package');

        $this->ioMock->shouldReceive('writeError')
            ->withArgs(static function ($string) {
                Assert::assertContains('Using version <info>', $string);
                Assert::assertContains('for <info>symfony/symfony</info>', $string);

                return true;
            });

        $linkMock = $this->mock(Link::class);
        $linkMock->shouldReceive('getTarget')
            ->once()
            ->andReturn('viserio/log');
        $linkMock->shouldReceive('getConstraint')
            ->once()
            ->andReturn('^1.0.0');

        $this->rootPackageMock->shouldReceive('getRequires')
            ->twice()
            ->andReturn([$linkMock]);

        $this->rootPackageMock->shouldReceive('getDevRequires')
            ->twice()
            ->andReturn([]);

        $name = 'symfony/symfony';

        $packageMock = $this->mock(PackageContract::class);
        $packageMock->shouldReceive('getPrettyName')
            ->once()
            ->andReturn($name);
        $packageMock->shouldReceive('getPrettyVersion')
            ->andReturn(null);

        $this->ioMock->shouldReceive('askAndValidate')
            ->once()
            ->with(
                \sprintf('Enter the version of <info>%s</info> to require (or leave blank to use the latest version): ', $name),
                \Mockery::type('closure')
            )
            ->andReturnFalse();

        $this->configMock->shouldReceive('get')
            ->with('sort-packages')
            ->andReturnFalse();

        $this->composerMock->shouldReceive('getConfig')
            ->andReturn($this->configMock);

        $this->rootPackageMock->shouldReceive('setRequires')
            ->once()
            ->withArgs(static function ($value) use ($name) {
                $keys = \array_keys($value);

                Assert::assertSame($name, $keys[1]);
                Assert::assertInstanceOf(Link::class, $value[$name]);

                return true;
            });

        $this->rootPackageMock->shouldReceive('setDevRequires')
            ->once()
            ->with([]);

        $manager = new InstallationManager($this->composerMock, $this->ioMock, $this->inputMock);

        $manager->install([$packageMock]);

        $this->delete($dirPath);
        \rmdir($dirPath);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    public function testInstallWithRequireAndPackageVersion(): void
    {
        $path    = __DIR__ . '/Fixture/composer.json';
        $dirPath = \dirname($path);

        @\mkdir($dirPath);
        @\file_put_contents($path, \json_encode(['require' => [], 'require-dev' => []]));
        \putenv('COMPOSER=' . $path);

        $this->ioMock->shouldReceive('writeError')
            ->with('Downloading https://repo.packagist.org/packages.json', true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('writeError')
            ->with(\Mockery::type('string'), true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('writeError')
            ->with('Updating composer.json');
        $this->ioMock->shouldReceive('writeError')
            ->with('Updating root package');

        $linkMock = $this->mock(Link::class);
        $linkMock->shouldReceive('getTarget')
            ->once()
            ->andReturn('viserio/log');
        $linkMock->shouldReceive('getConstraint')
            ->once()
            ->andReturn('^1.0.0');

        $this->rootPackageMock->shouldReceive('getRequires')
            ->twice()
            ->andReturn([$linkMock]);

        $this->rootPackageMock->shouldReceive('getDevRequires')
            ->twice()
            ->andReturn([]);

        $name = 'symfony/symfony';

        $packageMock = $this->mock(PackageContract::class);
        $packageMock->shouldReceive('getPrettyName')
            ->once()
            ->andReturn($name);
        $packageMock->shouldReceive('getPrettyVersion')
            ->andReturn($constraint = '^3.0.0');

        $this->ioMock->shouldReceive('askAndValidate')
            ->never()
            ->with(
                \sprintf('Enter the version of <info>%s</info> to require (or leave blank to use the latest version): ', $name),
                \Mockery::type('closure')
            );

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(\sprintf('Using version <info>%s</info> for <info>%s</info>', $constraint, $name));

        $this->configMock->shouldReceive('get')
            ->with('sort-packages')
            ->andReturnFalse();

        $this->composerMock->shouldReceive('getConfig')
            ->andReturn($this->configMock);

        $this->rootPackageMock->shouldReceive('setRequires')
            ->once()
            ->withArgs(static function ($value) use ($name) {
                $keys = \array_keys($value);

                Assert::assertSame($name, $keys[1]);
                Assert::assertInstanceOf(Link::class, $value[$name]);

                return true;
            });

        $this->rootPackageMock->shouldReceive('setDevRequires')
            ->once()
            ->with([]);

        $manager = new InstallationManager($this->composerMock, $this->ioMock, $this->inputMock);

        $manager->install([$packageMock]);

        $this->delete($dirPath);
        \rmdir($dirPath);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    private function delete(string $path): void
    {
        \array_map(function ($value): void {
            if (\is_dir($value)) {
                $this->delete($value);

                @\rmdir($value);
            } else {
                @\unlink($value);
            }
        }, \glob($path . \DIRECTORY_SEPARATOR . '*'));
    }
}
