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

trait ExpandTargetDirTrait
{
    /**
     * @param string[] $options
     */
    public static function expandTargetDir(array $options, string $target): string
    {
        $found = \preg_match('{%(.+?)%}', $target, $matches);

        if ($found !== 1) {
            return $target;
        }

        $option = \str_replace('_', '-', \strtolower($matches[1]));

        if (! isset($options[$option])) {
            return $matches[0];
        }

        return \str_replace($matches[0], \rtrim($options[$option], '/'), $target);
    }
}
