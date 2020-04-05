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

namespace Narrowspark\Automatic\Tests;

use Composer\IO\IOInterface;
use Mockery;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\Installer\InstallationManager;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\Lock;
use Narrowspark\Automatic\SkeletonGenerator;
use Narrowspark\Automatic\Tests\Fixture\ConsoleFixtureGenerator;
use Narrowspark\Automatic\Tests\Fixture\FrameworkDefaultFixtureGenerator;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Assert;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Common\Generator\AbstractGenerator
 * @covers \Narrowspark\Automatic\SkeletonGenerator
 *
 * @medium
 */
final class SkeletonGeneratorTest extends MockeryTestCase
{
    /** @var \Composer\IO\IOInterface|\Mockery\MockInterface */
    private $ioMock;

    /** @var \Mockery\MockInterface|\Narrowspark\Automatic\Installer\InstallationManager */
    private $installationManagerMock;

    /** @var \Mockery\MockInterface|\Narrowspark\Automatic\Lock */
    private $lockMock;

    /** @var \Narrowspark\Automatic\SkeletonGenerator */
    private $skeletonGenerator;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->ioMock = Mockery::mock(IOInterface::class);
        $this->installationManagerMock = Mockery::mock(InstallationManager::class);
        $this->lockMock = Mockery::mock(Lock::class);

        $this->skeletonGenerator = new SkeletonGenerator(
            $this->ioMock,
            $this->installationManagerMock,
            $this->lockMock,
            __DIR__,
            []
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $path = __DIR__ . '/Fixture/test.php';

        if (\file_exists($path)) {
            @\unlink($path);
        }
    }

    public function testRun(): void
    {
        $this->installationManagerMock->shouldReceive('install')
            ->once()
            ->withArgs(static function (array $requires, array $devRequires): bool {
                Assert::assertInstanceOf(Package::class, $requires[0]);
                Assert::assertIsArray($devRequires);

                return true;
            });
        $this->installationManagerMock->shouldReceive('run')
            ->once();

        $this->arrangeLock(
            [ConsoleFixtureGenerator::class => '%vendor_path%/Fixture/ConsoleFixtureGenerator.php'],
            ['test/generator' => [ConsoleFixtureGenerator::class]]
        );

        $this->ioMock->shouldReceive('select')
            ->once()
            ->with('Please select a skeleton:', ['console'], 'console')
            ->andReturn(0);
        $this->ioMock->shouldReceive('write')
            ->with("\nGenerating [console] skeleton.\n");

        $this->skeletonGenerator->run();
    }

    public function testRunWithDefault(): void
    {
        $this->installationManagerMock->shouldReceive('install')
            ->once()
            ->with([], []);
        $this->installationManagerMock->shouldReceive('run')
            ->once();

        $this->arrangeLock(
            [
                ConsoleFixtureGenerator::class => '%vendor_path%/Fixture/ConsoleFixtureGenerator.php',
                FrameworkDefaultFixtureGenerator::class => '%vendor_path%/Fixture/FrameworkDefaultFixtureGenerator.php',
            ],
            ['test/generator' => [ConsoleFixtureGenerator::class, FrameworkDefaultFixtureGenerator::class]]
        );

        $this->ioMock->shouldReceive('select')
            ->once()
            ->with('Please select a skeleton:', ['console', 'framework'], 'framework')
            ->andReturn(1);
        $this->ioMock->shouldReceive('write')
            ->with("\nGenerating [framework] skeleton.\n");

        $this->skeletonGenerator->run();
    }

    public function testRemove(): void
    {
        $this->lockMock->shouldReceive('get')
            ->once()
            ->with(SkeletonInstaller::LOCK_KEY)
            ->andReturn(['test/generator' => [ConsoleFixtureGenerator::class]]);

        $this->installationManagerMock->shouldReceive('uninstall')
            ->once()
            ->with(Mockery::type('array'), []);

        $this->lockMock->shouldReceive('read')
            ->once();
        $this->lockMock->shouldReceive('remove')
            ->once()
            ->with(Automatic::LOCK_CLASSMAP, 'test/generator');
        $this->lockMock->shouldReceive('remove')
            ->once()
            ->with(SkeletonInstaller::LOCK_KEY);
        $this->lockMock->shouldReceive('write')
            ->once();

        $this->skeletonGenerator->selfRemove();
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    /**
     * @param array<string, string>             $classmap
     * @param array<string, array<int, string>> $generators
     */
    protected function arrangeLock(array $classmap, array $generators): void
    {
        $this->lockMock->shouldReceive('get')
            ->once()
            ->with(Automatic::LOCK_CLASSMAP, 'test/generator')
            ->andReturn($classmap);

        $this->lockMock->shouldReceive('get')
            ->once()
            ->with(SkeletonInstaller::LOCK_KEY)
            ->andReturn($generators);
    }
}
