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

namespace Narrowspark\Automatic\Installer;

use Composer\Package\PackageInterface;

final class SkeletonInstaller extends AbstractInstaller
{
    /**
     * {@inheritdoc}
     */
    public const TYPE = 'automatic-skeleton';

    /**
     * {@inheritdoc}
     */
    public const LOCK_KEY = 'skeleton';

    /**
     * {@inheritdoc}
     */
    protected function removeFromLock(PackageInterface $package, string $key): void
    {
        $this->lock->remove($key);
    }
}
