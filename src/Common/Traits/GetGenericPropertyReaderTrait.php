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

use Closure;

trait GetGenericPropertyReaderTrait
{
    /**
     * Returns a callback that can read private variables from object.
     *
     * @return Closure
     */
    protected function getGenericPropertyReader(): Closure
    {
        $reader = function &(object $object, string $property) {
            $value = &Closure::bind(function &() use ($property) {
                return $this->{$property};
            }, $object, $object)->__invoke();

            return $value;
        };

        return $reader;
    }
}
