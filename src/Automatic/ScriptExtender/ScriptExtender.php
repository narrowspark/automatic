<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\ScriptExtender;

use Narrowspark\Automatic\Common\Contract\ScriptExtender as ScriptExtenderContract;

final class ScriptExtender implements ScriptExtenderContract
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
