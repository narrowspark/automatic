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

namespace Narrowspark\Automatic\Test;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Automatic\ScriptExtender\ScriptExtender;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
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
