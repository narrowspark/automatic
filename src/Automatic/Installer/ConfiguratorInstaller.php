<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Installer;

use Composer\Package\PackageInterface;

final class ConfiguratorInstaller extends AbstractInstaller
{
    /**
     * {@inheritdoc}
     */
    public const TYPE = 'automatic-configurator';

    /**
     * {@inheritdoc}
     */
    public const LOCK_KEY = 'configurators';

    /**
     * {@inheritdoc}
     */
    protected function removeFromLock(PackageInterface $package, string $key): void
    {
        $lockKeyArray = (array) $this->lock->get($key);
        $name         = $package->getName();

        if (isset($lockKeyArray[$name])) {
            unset($lockKeyArray[$name]);
        }

        $this->lock->add($key, $lockKeyArray);
    }
}
