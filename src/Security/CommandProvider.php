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
     *
     * @return \Narrowspark\Automatic\Security\Command\AuditCommand[]
     */
    public function getCommands(): array
    {
        return [new AuditCommand()];
    }
}
