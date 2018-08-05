<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\IO\IOInterface;
use Composer\Json\JsonManipulator;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Installer\InstallationManager;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\Lock;
use Narrowspark\Automatic\SkeletonGenerator;
use Narrowspark\Automatic\Test\Fixtures\ConsoleFixtureGenerator;
use Narrowspark\Automatic\Test\Fixtures\FrameworkDefaultFixtureGenerator;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 */
final class SkeletonGeneratorTest extends MockeryTestCase
{
    /**
     * @var \Composer\IO\IOInterface|\Mockery\MockInterface
     */
    private $ioMock;

    /**
     * @var \Narrowspark\Automatic\Installer\InstallationManager|\Mockery\MockInterface
     */
    private $installationManager;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->ioMock = $this->mock(IOInterface::class);
        $this->installationManager = $this->mock(InstallationManager::class);
    }

    public function testRun(): void
    {
        $this->installationManager->shouldReceive('install')
            ->once()
            ->with([], []);

        $skeletonGenerator = new SkeletonGenerator(
            [],
            ['narrowspark/skeleton' => [ConsoleFixtureGenerator::class]],
            $this->ioMock,
            $this->installationManager
        );

        $this->ioMock->shouldReceive('select')
            ->once()
            ->with('Please select a skeleton:', ['console'], 'console')
            ->andReturn(0);
        $this->ioMock->shouldReceive('write')
            ->with("\nGenerating [console] skeleton.\n");

        $skeletonGenerator->run();
    }

    public function testRunWithDefault(): void
    {
        $this->installationManager->shouldReceive('install')
            ->once()
            ->with([], []);

        $skeletonGenerator = new SkeletonGenerator(
            [],
            ['narrowspark/skeleton' => [ConsoleFixtureGenerator::class, FrameworkDefaultFixtureGenerator::class]],
            $this->ioMock,
            $this->installationManager
        );

        $this->ioMock->shouldReceive('select')
            ->once()
            ->with('Please select a skeleton:', ['console', 'framework'], 'framework')
            ->andReturn(1);
        $this->ioMock->shouldReceive('write')
            ->with("\nGenerating [framework] skeleton.\n");

        $skeletonGenerator->run();
    }

    public function testRemove(): void
    {
        $packageName       = 'narrowspark/skeleton';
        $skeletonGenerator = new SkeletonGenerator(
            [],
            [$packageName => [ConsoleFixtureGenerator::class]],
            $this->ioMock,
            $this->installationManager
        );

        $manipulatorMock = $this->mock(JsonManipulator::class);
        $lockMock        = $this->mock(Lock::class);

        $manipulatorMock->shouldReceive('removeSubNode')
            ->with('require', $packageName);
        $manipulatorMock->shouldReceive('removeSubNode')
            ->with('require-dev', $packageName);

        $lockMock->shouldReceive('remove')
            ->once()
            ->with(SkeletonInstaller::LOCK_KEY);
        $lockMock->shouldReceive('get')
            ->once()
            ->with(Automatic::LOCK_CLASSMAP)
            ->andReturn([]);
        $lockMock->shouldReceive('add')
            ->once()
            ->with(Automatic::LOCK_CLASSMAP, []);
        $lockMock->shouldReceive('write')
            ->once();

        $skeletonGenerator->remove($manipulatorMock, $lockMock);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
