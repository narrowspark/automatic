<?php
declare(strict_types=1);
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
