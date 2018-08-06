<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\IO\IOInterface;
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
     * @var \Mockery\MockInterface|\Narrowspark\Automatic\Installer\InstallationManager
     */
    private $installationManagerMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->ioMock                  = $this->mock(IOInterface::class);
        $this->installationManagerMock = $this->mock(InstallationManager::class);
    }

    public function testRun(): void
    {
        $this->installationManagerMock->shouldReceive('install')
            ->once()
            ->with([], []);

        $skeletonGenerator = new SkeletonGenerator(
            $this->ioMock,
            $this->installationManagerMock,
            [],
            [ConsoleFixtureGenerator::class]
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
        $this->installationManagerMock->shouldReceive('install')
            ->once()
            ->with([], []);

        $skeletonGenerator = new SkeletonGenerator(
            $this->ioMock,
            $this->installationManagerMock,
            [],
            [ConsoleFixtureGenerator::class, FrameworkDefaultFixtureGenerator::class]
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
        $skeletonGenerator = new SkeletonGenerator(
            $this->ioMock,
            $this->installationManagerMock,
            [],
            [ConsoleFixtureGenerator::class]
        );

        $package  = ['narrowspark/skeleton' => 'dev-master'];
        $lockMock = $this->mock(Lock::class);

        $lockMock->shouldReceive('remove')
            ->once()
            ->with(SkeletonInstaller::LOCK_KEY);

        $this->installationManagerMock->shouldReceive('uninstall')
            ->once()
            ->with($package);

        $lockMock->shouldReceive('get')
            ->once()
            ->with(Automatic::LOCK_CLASSMAP)
            ->andReturn([]);
        $lockMock->shouldReceive('add')
            ->once()
            ->with(Automatic::LOCK_CLASSMAP, []);
        $lockMock->shouldReceive('write')
            ->once();

        $skeletonGenerator->remove($lockMock, $package);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
