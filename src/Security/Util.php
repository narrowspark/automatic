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

use Composer\Factory;

final class Util
{
    /**
     * Private constructor; non-instantiable.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * Get the composer.lock file path.
     *
     * @return string
     */
    public static function getComposerLockFile(): string
    {
        return \substr(Factory::getComposerFile(), 0, -4) . 'lock';
    }
}
