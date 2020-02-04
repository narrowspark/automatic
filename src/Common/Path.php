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

final class Path
{
    /**
     * Path to the working directory.
     *
     * @var string
     */
    private $workingDirectory;

    /**
     * Create a new Path instance.
     */
    public function __construct(string $workingDirectory)
    {
        $this->workingDirectory = $workingDirectory;
    }

    /**
     * Get the working directory path.
     */
    public function getWorkingDir(): string
    {
        return $this->workingDirectory;
    }

    public function relativize(string $absolutePath): string
    {
        $relativePath = \str_replace($this->workingDirectory, '.', $absolutePath);

        return \is_dir($absolutePath) ? \rtrim($relativePath, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR : $relativePath;
    }

    public function concatenate(array $parts): string
    {
        $first = \array_shift($parts);

        return \array_reduce($parts, static function (string $initial, string $next): string {
            return \rtrim($initial, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . \ltrim($next, \DIRECTORY_SEPARATOR);
        }, $first);
    }
}
