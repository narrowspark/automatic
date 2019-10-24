<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\Automatic\Test\Common\Traits;

use Narrowspark\Automatic\Common\Traits\ExpandTargetDirTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
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
