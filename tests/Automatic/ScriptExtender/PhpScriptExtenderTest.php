<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Narrowspark\Automatic\ScriptExtender\PhpScriptExtender;
use PHPUnit\Framework\TestCase;

class PhpScriptExtenderTest extends TestCase
{
    /**
     * @var \Narrowspark\Automatic\ScriptExtender\PhpScriptExtender
     */
    private $extender;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->extender = new PhpScriptExtender();
    }

    public function testGetType(): void
    {
        static::assertSame('php-script', PhpScriptExtender::getType());
    }

    public function testExpand(): void
    {
        $output = $this->extender->expand('echo "hallo";');

        static::assertContains('/php', $output);
        static::assertContains('/php.ini', $output);
        static::assertContains('echo "hallo";', $output);
    }


    public function testExpandWithIniLoad(): void
    {
        // clear the composer env
        putenv('COMPOSER_ORIGINAL_INIS=');
        putenv('COMPOSER_ORIGINAL_INIS');

        $output = $this->extender->expand('echo "hallo";');

        static::assertContains('/php', $output);
        static::assertContains('/php.ini', $output);
        static::assertContains('echo "hallo";', $output);
    }
}
