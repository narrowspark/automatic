<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Test;

use Narrowspark\Automatic\Security\Util;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class UtilTest extends TestCase
{
    public function testGetComposerLockFile(): void
    {
        static::assertSame('./composer.lock', Util::getComposerLockFile());
    }
}
