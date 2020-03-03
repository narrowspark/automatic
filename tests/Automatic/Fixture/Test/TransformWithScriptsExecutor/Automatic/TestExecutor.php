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
