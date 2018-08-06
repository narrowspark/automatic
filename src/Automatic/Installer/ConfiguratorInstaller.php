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
    protected function saveToLockFile(array $autoload, PackageInterface $package, string $key): bool
    {
        $classes = $this->findClasses($autoload, $package);

        if ($classes === null) {
            return false;
        }

        $this->lock->add(
            $key,
            \array_merge(
                (array) $this->lock->get($key),
                [$package->getPrettyName() => $classes]
            )
        );

        $this->loader->load();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function removeFromLock(PackageInterface $package, string $key): void
    {
        $lockKeyArray = (array) $this->lock->get($key);
        $name         = $package->getPrettyName();

        if (isset($lockKeyArray[$name])) {
            unset($lockKeyArray[$name]);
        }

        $this->lock->add($key, $lockKeyArray);
    }
}
