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
