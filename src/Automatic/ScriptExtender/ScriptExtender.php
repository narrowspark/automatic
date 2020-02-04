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

namespace Narrowspark\Automatic\ScriptExtender;

use Narrowspark\Automatic\Common\ScriptExtender\AbstractScriptExtender;

final class ScriptExtender extends AbstractScriptExtender
{
    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'script';
    }

    /**
     * {@inheritdoc}
     */
    public function expand(string $cmd): string
    {
        return $cmd;
    }
}
