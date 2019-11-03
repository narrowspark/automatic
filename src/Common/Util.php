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

namespace Narrowspark\Automatic\Common;

use Composer\Composer;
use Composer\Factory;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use InvalidArgumentException;
use Narrowspark\Automatic\Common\Contract\Exception\RuntimeException;
use function file_get_contents;
use function preg_match;
use function substr;

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
     * Return the composer json file and json manipulator.
     *
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public static function getComposerJsonFileAndManipulator(): array
    {
        $json = new JsonFile(Factory::getComposerFile());
        $manipulator = new JsonManipulator(file_get_contents($json->getPath()));

        return [$json, $manipulator];
    }

    /**
     * Get the composer.lock file path.
     *
     * @return string
     */
    public static function getComposerLockFile(): string
    {
        return substr(Factory::getComposerFile(), 0, -4) . 'lock';
    }

    /**
     * Get the composer version.
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\RuntimeException
     *
     * @return string
     */
    public static function getComposerVersion(): string
    {
        preg_match('/\d+.\d+.\d+/m', Composer::VERSION, $matches);

        if ($matches !== null) {
            return $matches[0];
        }

        preg_match('/\d+.\d+.\d+/m', Composer::BRANCH_ALIAS_VERSION, $matches);

        if ($matches !== null) {
            return $matches[0];
        }

        throw new RuntimeException('No composer version found.');
    }
}
