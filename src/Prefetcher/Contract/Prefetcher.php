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

interface Prefetcher
{
    /**
     * Should the repo- and dir cache be populated.
     */
    public function populateRepoCacheDir(): void;

    public function prefetchComposerRepositories(): void;

    /**
     * @param \Composer\Installer\InstallerEvent|\Composer\Installer\PackageEvent $event
     */
    public function fetchAllFromOperations($event): void;
}
