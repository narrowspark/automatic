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

namespace Narrowspark\Automatic\Prefetcher\Contract;

use Narrowspark\Automatic\Common\Contract\Resettable as ResettableContract;

interface LegacyTagsManager extends ResettableContract
{
    /**
     * Add a legacy package constraint.
     *
     * @param string $name
     * @param string $require
     *
     * @return void
     */
    public function addConstraint(string $name, string $require): void;

    /**
     * Check if the provider is supported.
     *
     * @param string $file the composer provider file name
     *
     * @return bool
     */
    public function hasProvider(string $file): bool;

    /**
     * Remove legacy tags from packages.
     *
     * @param array $data
     *
     * @return array
     */
    public function removeLegacyTags(array $data): array;
}
