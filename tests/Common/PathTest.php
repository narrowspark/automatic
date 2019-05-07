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
    /**
     * @var \Narrowspark\Automatic\Common\Path
     */
    private $path;

    /**
     * {@inheritDoc}
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
}
