<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Automatic\ScriptExtender\ScriptExtender;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ScriptExtenderTest extends TestCase
{
    /**
     * @var \Narrowspark\Automatic\ScriptExtender\ScriptExtender
     */
    private $extender;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->extender = new ScriptExtender(new Composer(), new NullIO(), []);
    }

    public function testGetType(): void
    {
        $this->assertSame('script', ScriptExtender::getType());
    }

    public function testExpand(): void
    {
        $this->assertSame('php -v', $this->extender->expand('php -v'));
    }
}
