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

namespace Narrowspark\Automatic\Test;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Automatic\ScriptExtender\ScriptExtender;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\ScriptExtender\ScriptExtender
 *
 * @medium
 */
final class ScriptExtenderTest extends TestCase
{
    /** @var \Narrowspark\Automatic\ScriptExtender\ScriptExtender */
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
        self::assertSame('script', ScriptExtender::getType());
    }

    public function testExpand(): void
    {
        self::assertSame('php -v', $this->extender->expand('php -v'));
    }
}
