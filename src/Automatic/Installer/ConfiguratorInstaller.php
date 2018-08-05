<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Installer;

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
    protected function saveConfiguratorsToLockFile(array $autoload, string $name): array
    {
        $configurators = parent::saveConfiguratorsToLockFile($autoload, $name);

        $this->loader->load();

        return $configurators;
    }
}
