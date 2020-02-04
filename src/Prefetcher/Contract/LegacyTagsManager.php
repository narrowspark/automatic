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

namespace Narrowspark\Automatic\Prefetcher\Contract;

use Narrowspark\Automatic\Common\Contract\Resettable as ResettableContract;

interface LegacyTagsManager extends ResettableContract
{
    /**
     * Add a legacy package constraint.
     */
    public function addConstraint(string $name, string $require): void;

    /**
     * Check if the provider is supported.
     *
     * @param string $file the composer provider file name
     */
    public function hasProvider(string $file): bool;

    /**
     * Remove legacy tags from packages.
     */
    public function removeLegacyTags(array $data): array;
}
