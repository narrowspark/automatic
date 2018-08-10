<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Test;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Automatic\Common\ScriptExtender\PhpScriptExtender;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PhpScriptExtenderTest extends TestCase
{
    /**
     * @var \Narrowspark\Automatic\Common\ScriptExtender\PhpScriptExtender
     */
    private $extender;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->extender = new PhpScriptExtender(new Composer(), new NullIO(), []);
    }

    public function testGetType(): void
    {
        static::assertSame('php-script', PhpScriptExtender::getType());
    }

    public function testExpand(): void
    {
        $output = $this->extender->expand('echo "hallo";');

        static::assertContains('php', $output);
        static::assertContains('php.ini', $output);
        static::assertContains('echo "hallo";', $output);
    }

    public function testExpandWithIniLoad(): void
    {
        // clear the composer env
        \putenv('COMPOSER_ORIGINAL_INIS=');
        \putenv('COMPOSER_ORIGINAL_INIS');

        $output = $this->extender->expand('echo "hallo";');

        static::assertContains('php', $output);
        static::assertContains('php.ini', $output);
        static::assertContains('echo "hallo";', $output);
    }
}
