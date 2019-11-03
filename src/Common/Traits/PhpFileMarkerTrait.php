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

namespace Narrowspark\Automatic\Common\Traits;

use function file_get_contents;
use function is_file;
use function sprintf;
use function str_repeat;
use function strpos;

trait PhpFileMarkerTrait
{
    /**
     * Check if file is marked.
     *
     * @param string $packageName
     * @param string $file
     *
     * @return bool
     */
    protected function isFileMarked(string $packageName, string $file): bool
    {
        return is_file($file) && strpos(file_get_contents($file), sprintf('/** > %s **/', $packageName)) !== false;
    }

    /**
     * Mark file with given data.
     *
     * @param string $packageName
     * @param string $data
     * @param int    $spaceMultiplier
     *
     * @return string
     */
    protected function markData(string $packageName, string $data, int $spaceMultiplier = 4): string
    {
        $spaces = str_repeat(' ', $spaceMultiplier);

        return sprintf('%s/** > %s **/' . "\n" . '%s%s/** %s < **/' . "\n", $spaces, $packageName, $data, $spaces, $packageName);
    }
}
