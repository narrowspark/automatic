<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Command;

use Composer\Plugin\Capability\CommandProvider as CommandProviderContract;

/**
 * @internal
 */
final class CommandProvider implements CommandProviderContract
{
    /**
     * {@inheritdoc}
     */
    public function getCommands(): array
    {
        return [new AuditCommand()];
    }
}
