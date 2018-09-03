<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Command;

use Composer\Plugin\Capability\CommandProvider;

class AuditCommandProvider implements CommandProvider
{
    /**
     * {@inheritdoc}
     */
    public function getCommands(): array
    {
        return array(new AuditCommand());
    }
}
