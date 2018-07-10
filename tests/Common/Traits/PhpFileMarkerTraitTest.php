<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Common\Test\Traits;

use Narrowspark\Discovery\Common\Traits\PhpFileMarkerTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PhpFileMarkerTraitTest extends TestCase
{
    use PhpFileMarkerTrait;

    /**
     * @var string
     */
    private $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = \tempnam(\sys_get_temp_dir(), 'diac');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        @\unlink($this->path);
    }

    public function testIsFileMarked(): void
    {
        \file_put_contents($this->path, "<?php\n\n\$array = [\n/** > marked **/ 'test' /** < marked **/\n];\n");

        static::assertFalse($this->isFileMarked('test', $this->path));
        static::assertTrue($this->isFileMarked('marked', $this->path));
    }

    public function testMarkData(): void
    {
        \file_put_contents($this->path, $this->markData('test', '$arr = [];', 4));

        static::assertTrue($this->isFileMarked('test', $this->path));
    }
}
