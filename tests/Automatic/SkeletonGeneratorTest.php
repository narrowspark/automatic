<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\IO\IOInterface;
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
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->ioMock = $this->mock(IOInterface::class);
    }

    public function testRun(): void
    {
        $skeletonGenerator = new SkeletonGenerator(
            [],
            [ConsoleFixtureGenerator::class],
            $this->ioMock
        );

        $this->ioMock->shouldReceive('select')
            ->once()
            ->with('Please select a skeleton', ['console'], 'console')
            ->andReturn('console');

        $skeletonGenerator->run();
    }

    public function testRunWithDefault(): void
    {
        $skeletonGenerator = new SkeletonGenerator(
            [],
            [ConsoleFixtureGenerator::class, FrameworkDefaultFixtureGenerator::class],
            $this->ioMock
        );

        $this->ioMock->shouldReceive('select')
            ->once()
            ->with('Please select a skeleton', ['console', 'framework'], 'framework')
            ->andReturn('framework');

        $skeletonGenerator->run();
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
