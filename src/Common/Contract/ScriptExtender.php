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

namespace Narrowspark\Automatic\Common\Contract;

interface ScriptExtender
{
    /**
     * The script type.
     *
     * @return string
     */
    public static function getType(): string;

    /**
     * Expand the given cmd string.
     *
     * @param string $cmd
     *
     * @return string
     */
    public function expand(string $cmd): string;
}
