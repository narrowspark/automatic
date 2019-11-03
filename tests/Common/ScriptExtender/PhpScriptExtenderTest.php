<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\Automatic\Test\Common;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Automatic\Common\ScriptExtender\PhpScriptExtender;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use function putenv;

/**
 * @internal
 *
 * @small
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
        putenv('COMPOSER_ORIGINAL_INIS=');
        putenv('COMPOSER_ORIGINAL_INIS');

        $output = $this->extender->expand('echo "hallo";');

        self::assertStringContainsString('php', $output);
        self::assertStringContainsString('php.ini', $output);
        self::assertStringContainsString('echo "hallo";', $output);
    }
}
