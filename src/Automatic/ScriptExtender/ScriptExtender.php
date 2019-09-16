<?php

declare(strict_types=1);

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
