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

use Closure;

trait GetGenericPropertyReaderTrait
{
    /**
     * Returns a callback that can read private variables from object.
     */
    protected function getGenericPropertyReader(): Closure
    {
        return function &(object $object, string $property) {
            $value = &Closure::bind(function &() use ($property) {
                return $this->{$property};
            }, $object, $object)->__invoke();

            return $value;
        };
    }
}
