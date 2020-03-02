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

namespace Narrowspark\Automatic\Test\Common;

use Narrowspark\Automatic\Common\Path;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Common\Path
 *
 * @small
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
        self::assertSame(__DIR__, $this->path->getWorkingDir());
    }

    public function testRelativize(): void
    {
        self::assertSame(
            '.' . \DIRECTORY_SEPARATOR,
            $this->path->relativize(__DIR__)
        );
    }

    public function testConcatenateOnWindows(): void
    {
        self::assertEquals(
            'c:' . \DIRECTORY_SEPARATOR . 'my-project' . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'kernel.php',
            $this->path->concatenate(['c:' . \DIRECTORY_SEPARATOR . 'my-project', 'src' . \DIRECTORY_SEPARATOR, 'kernel.php'])
        );
    }

    /**
     * @dataProvider provideConcatenateCases
     */
    public function testConcatenate(string $part1, string $part2, string $expectedPath): void
    {
        $actualPath = $this->path->concatenate([$part1, $part2]);

        self::assertSame($expectedPath, $actualPath);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function provideConcatenateCases(): iterable
    {
        return [
            [__DIR__, 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt', __DIR__ . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt'],
            [__DIR__, \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt', __DIR__ . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt'],
            ['', 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt', \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt'],
            ['', \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt', \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt'],
            ['.', 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt', '.' . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt'],
        ];
    }
}
