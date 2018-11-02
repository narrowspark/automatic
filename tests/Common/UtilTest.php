<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Test;

use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Narrowspark\Automatic\Common\Util;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class UtilTest extends TestCase
{
    public function testGetComposerJsonFileAndManipulator(): void
    {
        [$json, $manipulator] = Util::getComposerJsonFileAndManipulator();

        $this->assertInstanceOf(JsonFile::class, $json);
        $this->assertInstanceOf(JsonManipulator::class, $manipulator);
    }

    public function testGetComposerLockFile(): void
    {
        $this->assertSame('./composer.lock', Util::getComposerLockFile());
    }
}
