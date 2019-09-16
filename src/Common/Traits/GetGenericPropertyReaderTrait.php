<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Common\Traits;

use Closure;

trait GetGenericPropertyReaderTrait
{
    /**
     * Returns a callback that can read private variables from object.
     *
     * @return \Closure
     */
    protected function getGenericPropertyReader(): Closure
    {
        $reader = function &($object, $property) {
            $value = &Closure::bind(function &() use ($property) {
                return $this->{$property};
            }, $object, $object)->__invoke();

            return $value;
        };

        return $reader;
    }
}
