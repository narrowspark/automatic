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

namespace Narrowspark\Automatic\Security\Tests;

use Narrowspark\Automatic\Security\Command\AuditCommand;
use Narrowspark\Automatic\Security\CommandProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Security\CommandProvider
 *
 * @small
 */
final class CommandProviderTest extends TestCase
{
    public function testGetCommands(): void
    {
        $provider = new CommandProvider();

        $commands = $provider->getCommands();

        self::assertInstanceOf(AuditCommand::class, $commands[0]);
    }
}
