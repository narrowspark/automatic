<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018-2020 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/automatic
 */

namespace Narrowspark\Automatic\Tests\Common\ScriptExtender;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Automatic\Common\ScriptExtender\PhpScriptExtender;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Common\ScriptExtender\AbstractScriptExtender
 * @covers \Narrowspark\Automatic\Common\ScriptExtender\PhpScriptExtender
 *
 * @medium
 */
final class PhpScriptExtenderTest extends TestCase
{
    /** @var \Narrowspark\Automatic\Common\ScriptExtender\PhpScriptExtender */
    private $extender;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->extender = new PhpScriptExtender(new Composer(), new NullIO(), []);
    }

    public function testClassIsNotFinal(): void
    {
        $reflection = new ReflectionClass(PhpScriptExtender::class);

        self::assertFalse($reflection->isFinal());
    }

    public function testGetType(): void
    {
        self::assertSame('php-script', PhpScriptExtender::getType());
    }

    public function testExpand(): void
    {
        $output = $this->extender->expand('echo "hallo";');

        self::assertStringContainsString('php', $output);
        self::assertStringContainsString('php.ini', $output);
        self::assertStringContainsString('echo "hallo";', $output);
    }

    public function testExpandWithIniLoad(): void
    {
        // clear the composer env
        \putenv('COMPOSER_ORIGINAL_INIS=');
        \putenv('COMPOSER_ORIGINAL_INIS');

        $output = $this->extender->expand('echo "hallo";');

        self::assertStringContainsString('php', $output);
        self::assertStringContainsString('php.ini', $output);
        self::assertStringContainsString('echo "hallo";', $output);
    }
}
