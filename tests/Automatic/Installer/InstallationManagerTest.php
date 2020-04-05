<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018-2020 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/automatic
 */

namespace Narrowspark\Automatic\Tests\Installer;

use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Mockery;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Installer\InstallationManager;
use Narrowspark\Automatic\Tests\Traits\ArrangeComposerClassesTrait;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Assert;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Common\Installer\AbstractInstallationManager
 * @covers \Narrowspark\Automatic\Installer\InstallationManager
 *
 * @medium
 */
final class InstallationManagerTest extends MockeryTestCase
{
    use ArrangeComposerClassesTrait;

    /** @var \Composer\Package\Package|\Mockery\MockInterface */
    private $rootPackageMock;

    /** @var \Composer\Repository\RepositoryInterface|\Mockery\MockInterface */
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

        $this->rootPackageMock = Mockery::mock(RootPackageInterface::class);
        $this->rootPackageMock->shouldReceive('getMinimumStability')
            ->andReturn(null);

        $this->localRepositoryMock = Mockery::mock(RepositoryInterface::class);

        $repositoryMock = Mockery::mock(RepositoryManager::class);
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

    /**
     * @group network
     */
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

    /**
     * @group network
     */
    public function testInstallWithRequireAndNoPackageVersion(): void
    {
        $path = \dirname(__DIR__) . '/Fixture/install_composer.json';

        @\file_put_contents($path, \json_encode(['require' => [], 'require-dev' => []]));
        \putenv('COMPOSER=' . $path);

        $this->ioMock->shouldReceive('writeError')
            ->with('Downloading https://repo.packagist.org/packages.json', true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('writeError')
            ->with(Mockery::type('string'), true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('writeError')
            ->with('Updating composer.json');
        $this->ioMock->shouldReceive('writeError')
            ->with('Updating root package');

        $this->ioMock->shouldReceive('writeError')
            ->withArgs(static function ($string) {
                Assert::assertStringContainsString('Using version <info>', $string);
                Assert::assertStringContainsString('for <info>symfony/symfony</info>', $string);

                return true;
            });

        $linkMock = Mockery::mock(Link::class);
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

        $packageMock = Mockery::mock(PackageContract::class);
        $packageMock->shouldReceive('getPrettyName')
            ->once()
            ->andReturn($name);
        $packageMock->shouldReceive('getPrettyVersion')
            ->andReturn(null);

        $this->ioMock->shouldReceive('askAndValidate')
            ->once()
            ->with(
                \sprintf('Enter the version of <info>%s</info> to require (or leave blank to use the latest version): ', $name),
                Mockery::type('closure')
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

        \unlink($path);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    /**
     * @group network
     */
    public function testInstallWithRequireAndPackageVersion(): void
    {
        $path = \dirname(__DIR__) . '/Fixture/install_composer.json';

        @\file_put_contents($path, \json_encode(['require' => [], 'require-dev' => []]));
        \putenv('COMPOSER=' . $path);

        $this->ioMock->shouldReceive('writeError')
            ->with('Downloading https://repo.packagist.org/packages.json', true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('writeError')
            ->with(Mockery::type('string'), true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('writeError')
            ->with('Updating composer.json');
        $this->ioMock->shouldReceive('writeError')
            ->with('Updating root package');

        $linkMock = Mockery::mock(Link::class);
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

        $packageMock = Mockery::mock(PackageContract::class);
        $packageMock->shouldReceive('getPrettyName')
            ->once()
            ->andReturn($name);
        $packageMock->shouldReceive('getPrettyVersion')
            ->andReturn($constraint = '^3.0.0');

        $this->ioMock->shouldReceive('askAndValidate')
            ->never()
            ->with(
                \sprintf('Enter the version of <info>%s</info> to require (or leave blank to use the latest version): ', $name),
                Mockery::type('closure')
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

        \unlink($path);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    /**
     * @group network
     */
    public function testUninstallWithoutRequireAndRequireDev(): void
    {
        $path = \dirname(__DIR__) . '/Fixture/install_composer.json';

        @\file_put_contents($path, \json_encode(['require' => [], 'require-dev' => []]));
        \putenv('COMPOSER=' . $path);

        $this->ioMock->shouldReceive('writeError')
            ->atLeast()
            ->once();

        $this->rootPackageMock->shouldReceive('getRequires')
            ->once()
            ->andReturn([]);
        $this->rootPackageMock->shouldReceive('getDevRequires')
            ->once()
            ->andReturn([]);
        $this->rootPackageMock->shouldReceive('setRequires')
            ->once()
            ->with([]);
        $this->rootPackageMock->shouldReceive('setDevRequires')
            ->once()
            ->with([]);

        $this->localRepositoryMock->shouldReceive('getPackages')
            ->andReturn([]);

        $manager = new InstallationManager($this->composerMock, $this->ioMock, $this->inputMock);

        $manager->uninstall([]);

        \unlink($path);

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
