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

use Narrowspark\Automatic\Common\Traits\ExpandTargetDirTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Common\Traits\ExpandTargetDirTrait
 *
 * @small
 */
final class ExpandTargetDirTraitTest extends TestCase
{
    use ExpandTargetDirTrait;

    public function testItCanIdentifyVarsInTargetDir(): void
    {
        self::assertSame('bar', self::expandTargetDir(['foo' => 'bar/'], '%foo%'));
        self::assertSame('%foo_test%', self::expandTargetDir(['foo' => 'bar/'], '%foo_test%'));
    }
}
