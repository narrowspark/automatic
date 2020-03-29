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

namespace Narrowspark\Automatic\Tests\Common\Traits;

use Narrowspark\Automatic\Common\Traits\PhpFileMarkerTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Common\Traits\PhpFileMarkerTrait
 *
 * @medium
 */
final class PhpFileMarkerTraitTest extends TestCase
{
    use PhpFileMarkerTrait;

    /** @var string */
    private $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = (string) \tempnam(\sys_get_temp_dir(), 'diac');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        @\unlink($this->path);
    }

    public function testIsFileMarked(): void
    {
        \file_put_contents($this->path, "<?php\n\n\$array = [\n/** > marked **/ 'test' /** < marked **/\n];\n");

        self::assertFalse($this->isFileMarked('test', $this->path));
        self::assertTrue($this->isFileMarked('marked', $this->path));
    }

    public function testMarkData(): void
    {
        \file_put_contents($this->path, $this->markData('test', '$arr = [];', 4));

        self::assertTrue($this->isFileMarked('test', $this->path));
    }
}
