<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Narrowspark\Automatic\Common\Util as CommonUtil;

final class Util
{
    /**
     * Get the automatic.lock file path.
     *
     * @return string
     */
    public static function getAutomaticLockFile(): string
    {
        return \str_replace('composer', Automatic::COMPOSER_EXTRA_KEY, CommonUtil::getComposerLockFile());
    }
}
