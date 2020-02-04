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

use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Narrowspark\Automatic\Common\Util;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Common\Util
 *
 * @medium
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
