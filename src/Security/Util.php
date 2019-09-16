<?php

declare(strict_types=1);

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
