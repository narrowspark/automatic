<?php

declare(strict_types=1);

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
