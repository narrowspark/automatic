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

namespace Narrowspark\Automatic\Security\Test;

use Narrowspark\Automatic\Security\Command\AuditCommand;
use Narrowspark\Automatic\Security\CommandProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
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
