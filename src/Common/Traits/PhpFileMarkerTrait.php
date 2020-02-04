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

namespace Narrowspark\Automatic\Common\Traits;

trait PhpFileMarkerTrait
{
    /**
     * Check if file is marked.
     */
    protected function isFileMarked(string $packageName, string $file): bool
    {
        return \is_file($file) && \strpos(\file_get_contents($file), \sprintf('/** > %s **/', $packageName)) !== false;
    }

    /**
     * Mark file with given data.
     */
    protected function markData(string $packageName, string $data, int $spaceMultiplier = 4): string
    {
        $spaces = \str_repeat(' ', $spaceMultiplier);

        return \sprintf("%s/** > %s **/\n%s%s/** %s < **/\n", $spaces, $packageName, $data, $spaces, $packageName);
    }
}
