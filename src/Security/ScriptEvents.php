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

final class ScriptEvents
{
    /**
     * The POST_MESSAGES event occurs after a package is installed or updated.
     *
     * The event listener method receives a Composer\Script\Event instance.
     *
     * @var string
     */
    public const POST_MESSAGES = 'post-messages';
}
