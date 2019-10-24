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
