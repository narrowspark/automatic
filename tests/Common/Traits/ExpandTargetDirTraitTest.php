<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Test\Traits;

use Narrowspark\Automatic\Common\Traits\ExpandTargetDirTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ExpandTargetDirTraitTest extends TestCase
{
    use ExpandTargetDirTrait;

    public function testItCanIdentifyVarsInTargetDir(): void
    {
        static::assertSame('bar', self::expandTargetDir(['foo' => 'bar/'], '%foo%'));
        static::assertSame('%foo_test%', self::expandTargetDir(['foo' => 'bar/'], '%foo_test%'));
    }
}
