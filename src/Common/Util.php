<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common;

use Composer\Factory;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;

final class Util
{
    /**
     * @var string
     */
    public const AUTOMATIC = 'automatic';

    /**
     * Return the composer json file and json manipulator.
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    public static function getComposerJsonFileAndManipulator(): array
    {
        $json        = new JsonFile(Factory::getComposerFile());
        $manipulator = new JsonManipulator(\file_get_contents($json->getPath()));

        return [$json, $manipulator];
    }

    /**
     * Get the automatic.lock file path.
     *
     * @return string
     */
    public static function getAutomaticLockFile(): string
    {
        return \str_replace('composer', self::AUTOMATIC, self::getComposerLockFile());
    }

    /**
     * Get the composer.lock file path.
     *
     * @return string
     */
    public static function getComposerLockFile(): string
    {
        return \mb_substr(Factory::getComposerFile(), 0, -4) . 'lock';
    }
}
