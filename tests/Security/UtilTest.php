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

namespace Narrowspark\Automatic\Security\Test;

use Narrowspark\Automatic\Security\Util;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
final class UtilTest extends TestCase
{
    public function testGetComposerLockFile(): void
    {
        self::assertSame('./composer.lock', Util::getComposerLockFile());
    }
}
