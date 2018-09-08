<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Installer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\ClassFinder;
use Narrowspark\Automatic\Common\Contract\Exception\UnexpectedValueException;
use Narrowspark\Automatic\Lock;

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
     * A lock instance.
     *
     * @var \Narrowspark\Automatic\Lock
     */
    protected $lock;

    /**
     * A path class loader instance.
     *
     * @var \Narrowspark\Automatic\Common\ClassFinder
     */
    protected $loader;

    /**
     * Create a new Installer instance.
     *
     * @param \Composer\IO\IOInterface                  $io
     * @param \Composer\Composer                        $composer
     * @param \Narrowspark\Automatic\Lock               $lock
     * @param \Narrowspark\Automatic\Common\ClassFinder $loader
     */
    public function __construct(IOInterface $io, Composer $composer, Lock $lock, ClassFinder $loader)
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

        if ($this->saveToLockFile($autoload, $package, static::LOCK_KEY) === false) {
            // Rollback installation
            $this->io->writeError('Installation failed, rolling back');

            $this->uninstall($repo, $package);
        } else {
            $this->addToClassMap($package);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(
        InstalledRepositoryInterface $repo,
        PackageInterface $initial,
        PackageInterface $target
    ): void {
        parent::update($repo, $initial, $target);

        $this->saveToLockFile($target->getAutoload(), $target, static::LOCK_KEY);
        $this->addToClassMap($target);
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package): void
    {
        parent::uninstall($repo, $package);

        $this->removeFromLock($package, static::LOCK_KEY);

        $this->lock->remove(Automatic::LOCK_CLASSMAP, $package->getName());
    }

    /**
     * Finds all class in given namespace.
     *
     * @param array                              $autoload
     * @param \Composer\Package\PackageInterface $package
     *
     * @return null|array
     */
    protected function findClasses(array $autoload, PackageInterface $package): ?array
    {
        $classes = [];

        $this->loader
            ->setComposerAutoload($package->getName(), $autoload)
            ->find();

        foreach ($this->loader->getClasses() as $class => $path) {
            $classes[] = $class;
        }

        if (\count($classes) === 0) {
            return null;
        }

        return $classes;
    }

    /**
     * Save values to the automatic lock file.
     *
     * @param array                              $autoload
     * @param \Composer\Package\PackageInterface $package
     * @param string                             $key
     *
     * @return bool FALSE if saving to lock failed, TRUE if anything is alright
     */
    protected function saveToLockFile(array $autoload, PackageInterface $package, string $key): bool
    {
        $classes = $this->findClasses($autoload, $package);

        if ($classes === null) {
            return false;
        }

        $this->lock->addSub($key, $package->getName(), $classes);

        return true;
    }

    /**
     * Remove values from the automatic lock file.
     *
     * @param \Composer\Package\PackageInterface $package
     * @param string                             $key
     *
     * @return void
     */
    abstract protected function removeFromLock(PackageInterface $package, string $key): void;

    /**
     * Adds found classes to the automatic classmap.
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @return void
     */
    protected function addToClassMap(PackageInterface $package): void
    {
        $classMap = \array_map(function (string $value) {
            return \str_replace($this->vendorDir, '%vendor_path%', $value);
        }, $this->loader->getAll());

        $this->lock->addSub(Automatic::LOCK_CLASSMAP, $package->getName(), $classMap);
    }
}
