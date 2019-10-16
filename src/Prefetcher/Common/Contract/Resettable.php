<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Prefetcher\Common\Contract;

/**
 * This file is automatically generated, dont change this file, otherwise the changes are lost after the next mirror update.
 *
 * @codeCoverageIgnore
 * @internal
 */
interface Resettable
{
    /**
     * Provides a way to reset an object to its initial state.
     *
     * When calling the "reset()" method on an object, it should be put back to its
     * initial state. This usually means clearing any internal buffers and forwarding
     * the call to internal dependencies. All properties of the object should be put
     * back to the same state it had when it was first ready to use.
     *
     * @return void
     */
    public function reset(): void;
}