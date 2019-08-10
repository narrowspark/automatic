<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Test;

use Narrowspark\Automatic\Common\Path;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PathTest extends TestCase
{
    /** @var \Narrowspark\Automatic\Common\Path */
    private $path;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->path = new Path(__DIR__);
    }

    public function testGetWorkingDir(): void
    {
        $this->assertSame(__DIR__, $this->path->getWorkingDir());
    }

    public function testRelativize(): void
    {
        $this->assertSame(
            '.' . \DIRECTORY_SEPARATOR,
            $this->path->relativize(__DIR__)
        );
    }

    public function testConcatenateOnWindows(): void
    {
        $this->assertEquals(
            'c:' . \DIRECTORY_SEPARATOR . 'my-project' . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'kernel.php',
            $this->path->concatenate(['c:' . \DIRECTORY_SEPARATOR . 'my-project', 'src' . \DIRECTORY_SEPARATOR, 'kernel.php'])
        );
    }

    /**
     * @dataProvider providePathsForConcatenation
     *
     * @param string $part1
     * @param string $part2
     * @param string $expectedPath
     */
    public function testConcatenate(string $part1, string $part2, string $expectedPath): void
    {
        $actualPath = $this->path->concatenate([$part1, $part2]);

        $this->assertSame($expectedPath, $actualPath);
    }

    /**
     * @return array
     */
    public function providePathsForConcatenation(): array
    {
        return [
            [__DIR__, 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt', __DIR__ . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt'],
            [__DIR__, \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt', __DIR__ . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt'],
            ['', 'foo/bar.txt', \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt'],
            ['', \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt', \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt'],
            ['.', 'foo/bar.txt', '.' . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt'],
        ];
    }
}
