<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security;

use Composer\Plugin\Capability\CommandProvider as CommandProviderContract;
use Narrowspark\Automatic\Security\Command\AuditCommand;

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
