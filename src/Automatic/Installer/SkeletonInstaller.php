<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Installer;

use Composer\Package\PackageInterface;

class SkeletonInstaller extends AbstractInstaller
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
    protected function saveToLockFile(array $autoload, PackageInterface $package, string $key): bool
    {
        $classes = $this->findClasses($autoload, $package);

        if ($classes === null) {
            return false;
        }

        $this->lock->add(
            $key,
            [
                'name'       => $package->getName(),
                'version'    => $package->getPrettyVersion(),
                'generators' => $classes,
            ]
        );

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function removeFromLock(PackageInterface $package, string $key): void
    {
        $this->lock->add($key, []);
    }
}
