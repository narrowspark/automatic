<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

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
    protected function setUp()
    {
        parent::setUp();

        $this->extender = new ScriptExtender();
    }

    public function testGetType(): void
    {
        static::assertSame('script', ScriptExtender::getType());
    }

    public function testExpand(): void
    {
        static::assertSame('php -v', $this->extender->expand('php -v'));
    }
}
