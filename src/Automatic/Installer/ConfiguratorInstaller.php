<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Installer;

use Composer\Package\PackageInterface;

final class ConfiguratorInstaller extends AbstractInstaller
{
    /**
     * {@inheritDoc}
     */
    public const TYPE = 'automatic-configurator';

    /**
     * {@inheritDoc}
     */
    public const LOCK_KEY = 'configurators';

    /**
     * {@inheritDoc}
     */
    protected function removeFromLock(PackageInterface $package, string $key): void
    {
        $this->lock->remove($key, $package->getName());
    }
}
