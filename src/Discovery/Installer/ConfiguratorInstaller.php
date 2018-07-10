<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Installer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Narrowspark\Discovery\ClassFinder;
use Narrowspark\Discovery\Common\Contract\Exception\UnexpectedValueException;
use Narrowspark\Discovery\Lock;

class ConfiguratorInstaller extends LibraryInstaller
{
    /**
     * @var string
     */
    public const TYPE = 'discovery-configurator';

    /**
     * @var string
     */
    public const LOCK_KEY = 'configurators';

    /**
     * A lock instance.
     *
     * @var \Narrowspark\Discovery\Lock
     */
    private $lock;

    /**
     * {@inheritdoc}
     */
    public function __construct(IOInterface $io, Composer $composer, Lock $lock)
    {
        parent::__construct($io, $composer, self::TYPE);

        $this->lock = $lock;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($packageType): bool
    {
        return $packageType === self::TYPE;
    }

    /**
     * {@inheritdoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): void
    {
        $autoload = $package->getAutoload();

        if (\count($autoload['psr-4']) === 0) {
            throw new UnexpectedValueException('Error while installing "' . $package->getPrettyName() . '", discovery-configurator packages should have a namespace defined in their psr4 key to be usable.');
        }

        parent::install($repo, $package);

        $configurators = $this->saveConfiguratorsToLockFile($autoload, $package->getPrettyName());

        if (empty($configurators)) {
            // Rollback installation
            $this->io->writeError('Configurator installation failed, rolling back');

            $this->uninstall($repo, $package);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target): void
    {
        parent::update($repo, $initial, $target);

        $autoload = $target->getAutoload();

        $this->saveConfiguratorsToLockFile($autoload, $target->getPrettyName());
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package): void
    {
        parent::uninstall($repo, $package);

        $this->lock->remove(self::LOCK_KEY);
    }

    /**
     * Finds all class in given namespace and save it to discovery lock file.
     *
     * @param array  $autoload
     * @param string $name
     *
     * @return array
     */
    protected function saveConfiguratorsToLockFile(array $autoload, string $name): array
    {
        $psr4Namespaces = $autoload['psr-4'];

        $configurators = [];
        $basePath      = \rtrim($this->vendorDir, '/') . '/' . $name;

        foreach ((array) $psr4Namespaces as $psr4FolderPath) {
            $fullPath = \rtrim($basePath . '/' . $psr4FolderPath, '/');

            foreach (ClassFinder::find($fullPath) as $path => $class) {
                $configurators[\str_replace($this->vendorDir, '', $path)] = $class;
            }
        }

        if (\count($configurators) === 0) {
            return [];
        }

        if ($this->lock->has(self::LOCK_KEY)) {
            $configurators = \array_merge($this->lock->get(self::LOCK_KEY), $configurators);
        }

        $this->lock->add(self::LOCK_KEY, $configurators);

        return $configurators;
    }
}
