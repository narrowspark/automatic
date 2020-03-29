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

namespace Narrowspark\Automatic\Common;

use Composer\Composer;
use Composer\Factory;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use InvalidArgumentException;
use Narrowspark\Automatic\Common\Contract\Exception\RuntimeException;

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
     * @return \Composer\Json\JsonFile[]|\Composer\Json\JsonManipulator[]
     */
    public static function getComposerJsonFileAndManipulator(): array
    {
        $json = new JsonFile(Factory::getComposerFile());
        $manipulator = new JsonManipulator(\file_get_contents($json->getPath()));

        return [$json, $manipulator];
    }

    /**
     * Get the composer.lock file path.
     */
    public static function getComposerLockFile(): string
    {
        return \substr(Factory::getComposerFile(), 0, -4) . 'lock';
    }

    /**
     * Get the composer version.
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\RuntimeException
     */
    public static function getComposerVersion(): string
    {
        \preg_match('/\d+.\d+.\d+/m', Composer::VERSION, $versionMatches);

        if ($versionMatches !== null) {
            return $versionMatches[0];
        }

        \preg_match('/\d+.\d+.\d+/m', Composer::BRANCH_ALIAS_VERSION, $branchAliasMatches);

        if ($branchAliasMatches !== null) {
            return $branchAliasMatches[0];
        }

        throw new RuntimeException('No composer version found.');
    }
}
