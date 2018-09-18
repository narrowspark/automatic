<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Narrowspark\Automatic\Util;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class UtilTest extends TestCase
{
    public function testGetAutomaticLockFile(): void
    {
        static::assertSame('./automatic.lock', Util::getAutomaticLockFile());
    }
}
