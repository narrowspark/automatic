<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Common\Test;

use Narrowspark\Discovery\Common\Path;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PathTest extends TestCase
{
    /**
     * @var \Narrowspark\Discovery\Common\Path
     */
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
            './',
            $this->path->relativize(__DIR__)
        );
    }

    public function testConcatenateOnWindows(): void
    {
        $this->assertEquals(
            'c:\\my-project/src/kernel.php',
            $this->path->concatenate(['c:\\my-project', 'src/', 'kernel.php'])
        );
    }
}
