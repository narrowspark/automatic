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

namespace Narrowspark\Automatic\Test\Fixture\Test\TransformWithScriptsExecutor\Automatic;

use Narrowspark\Automatic\Common\ScriptExtender\AbstractScriptExtender;

final class TestExecutor extends AbstractScriptExtender
{
    public static function getType(): string
    {
        return 'test';
    }

    public function expand(string $cmd): string
    {
    }
}
