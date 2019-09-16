<?php

declare(strict_types=1);

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
