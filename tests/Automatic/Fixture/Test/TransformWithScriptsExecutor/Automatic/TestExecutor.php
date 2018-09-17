<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Fixture\Test\TransformWithScriptsExecutor\Automatic;

use Narrowspark\Automatic\Common\ScriptExtender\AbstractScriptExtender;

class TestExecutor extends AbstractScriptExtender
{
    public static function getType(): string
    {
        return 'test';
    }

    public function expand(string $cmd): string
    {
    }
}
