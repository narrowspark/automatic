<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Installer;

use Composer\Package\PackageInterface;

class SkeletonInstaller extends AbstractInstaller
{
    /**
     * {@inheritDoc}
     */
    public const TYPE = 'automatic-skeleton';

    /**
     * {@inheritDoc}
     */
    public const LOCK_KEY = 'skeleton';

    /**
     * {@inheritDoc}
     */
    protected function removeFromLock(PackageInterface $package, string $key): void
    {
        $this->lock->remove($key);
    }
}
