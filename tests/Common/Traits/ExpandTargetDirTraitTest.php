<?php

declare(strict_types=1);

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
