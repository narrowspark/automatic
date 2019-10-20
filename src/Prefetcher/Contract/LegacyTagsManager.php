<?php

declare(strict_types=1);

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
