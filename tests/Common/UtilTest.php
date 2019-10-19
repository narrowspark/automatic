<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Test\Common;

use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Narrowspark\Automatic\Common\Util;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
final class UtilTest extends TestCase
{
    public function testGetComposerJsonFileAndManipulator(): void
    {
        [$json, $manipulator] = Util::getComposerJsonFileAndManipulator();

        self::assertInstanceOf(JsonFile::class, $json);
        self::assertInstanceOf(JsonManipulator::class, $manipulator);
    }

    public function testGetComposerLockFile(): void
    {
        self::assertSame('./composer.lock', Util::getComposerLockFile());
    }
}
