<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Installer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Narrowspark\Automatic\Common\Contract\Exception\UnexpectedValueException;
use Narrowspark\Automatic\Lock;
use Narrowspark\Automatic\PathClassLoader;

abstract class AbstractInstaller extends LibraryInstaller
{
    /**
     * @var string
     */
    public const TYPE = null;

    /**
     * @var string
     */
    public const LOCK_KEY = null;

    /**
     * Overwrite found automatic.lock values.
     *
     * @var bool
     */
    protected const OVERWRITE_LOCK = false;

    /**
     * A lock instance.
     *
     * @var \Narrowspark\Automatic\Lock
     */
    private $lock;

    /**
     * A path class loader instance.
     *
     * @var \Narrowspark\Automatic\PathClassLoader
     */
    private $loader;

    /**
     * Create a new Installer instance.
     *
     * @param \Composer\IO\IOInterface               $io
     * @param \Composer\Composer                     $composer
     * @param \Narrowspark\Automatic\Lock            $lock
     * @param \Narrowspark\Automatic\PathClassLoader $loader
     */
    public function __construct(IOInterface $io, Composer $composer, Lock $lock, PathClassLoader $loader)
    {
        parent::__construct($io, $composer, static::TYPE);

        $this->lock   = $lock;
        $this->loader = $loader;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($packageType): bool
    {
        return $packageType === static::TYPE;
    }

    /**
     * {@inheritdoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): void
    {
        $autoload = $package->getAutoload();

        if (\count($autoload['psr-4']) === 0) {
            throw new UnexpectedValueException(\sprintf(
                'Error while installing [%s], %s packages should have a namespace defined in their psr4 key to be usable.',
                $package->getPrettyName(),
                static::TYPE
            ));
        }

        parent::install($repo, $package);

        $configurators = $this->saveConfiguratorsToLockFile($autoload, $package->getPrettyName());

        if (empty($configurators)) {
            // Rollback installation
            $this->io->writeError('Installation failed, rolling back');

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

        $this->lock->remove(static::LOCK_KEY);
    }

    /**
     * Finds all class in given namespace and save it to automatic lock file.
     *
     * @param array  $autoload
     * @param string $name
     *
     * @return array
     */
    protected function saveConfiguratorsToLockFile(array $autoload, string $name): array
    {
        $configurators = [];
        $basePath      = \rtrim($this->vendorDir, '/') . '/' . $name;

        $psr4 = \array_map(function ($path) use ($basePath) {
            return \rtrim($basePath . '/' . $path, '/');
        }, (array) $autoload['psr-4']);

        $this->loader->find($psr4);

        foreach ($this->loader->getClasses() as $path => $class) {
            $configurators[\str_replace($this->vendorDir, '', $path)] = $class;
        }

        if (\count($configurators) === 0) {
            return [];
        }

        if ($this->lock->has(static::LOCK_KEY)) {
            if (static::OVERWRITE_LOCK) {
                $this->io->writeError(\sprintf('Automatic lock key [%s] was overwritten.', static::LOCK_KEY));
                $this->lock->remove(static::LOCK_KEY);
            } else {
                $configurators = \array_merge((array) $this->lock->get(static::LOCK_KEY), $configurators);
            }
        }

        $this->lock->add(static::LOCK_KEY, $configurators);

        return $configurators;
    }
}
