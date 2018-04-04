<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Traits;

use Narrowspark\Discovery\Traits\ExpandTargetDirTrait;
use PHPUnit\Framework\TestCase;

class ExpandTargetDirTraitTest extends TestCase
{
    use ExpandTargetDirTrait;

    public function testItCanIdentifyVarsInTargetDir(): void
    {
        $options = ['foo' => 'bar/'];

        $expandedTargetDir = $this->expandTargetDir($options, '%foo%');

        self::assertSame('bar', $expandedTargetDir);
    }
}
