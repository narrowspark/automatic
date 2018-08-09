<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Package\PackageInterface;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Package;

class OperationsResolver
{
    /**
     * A lock instance.
     *
     * @var \Narrowspark\Automatic\Lock
     */
    private $lock;

    /**
     * Name of the parent package.
     *
     * @var string
     */
    private $parentName;

    /**
     * Create a new OperationsResolver instance.
     *
     * @param \Narrowspark\Automatic\Lock $lock
     */
    public function __construct(Lock $lock)
    {
        $this->lock = $lock;
    }

    /**
     * Set the parent package name.
     * This is used for the "extraDependencyOf" key.
     *
     * @param string $name
     *
     * @return void
     */
    public function setParentPackageName(string $name): void
    {
        $this->parentName = $name;
    }

    /**
     * Resolve packages from composer operations;.
     *
     * @param \Composer\DependencyResolver\Operation\OperationInterface[] $operations
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package[]
     */
    public function resolve(array $operations): array
    {
        $packages = [];

        foreach ($operations as $i => $operation) {
            $o = PackageContract::INSTALL_OPERATION;

            if ($operation instanceof UpdateOperation) {
                $composerPackage = $operation->getTargetPackage();
                $o               = PackageContract::UPDATE_OPERATION;
            } else {
                if ($operation instanceof UninstallOperation) {
                    $o = PackageContract::UNINSTALL_OPERATION;
                }

                $composerPackage = $operation->getPackage();
            }

            if (! isset($composerPackage->getExtra()['automatic'])) {
                continue;
            }

            $name = $composerPackage->getName();

            if ($operation instanceof UninstallOperation && $this->lock->has($name)) {
                $package = Package::createFromLock($name, (array) $this->lock->get($name));
            } else {
                $package = $this->createAutomaticPackage($composerPackage);
            }

            $package->setOperation($o);

            $packages[$name] = $package;
        }

        return $packages;
    }

    /**
     * Get a pretty package version.
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @return string
     */
    private function getPackageVersion(PackageInterface $package): string
    {
        $version = $package->getPrettyVersion();
        $extra   = $package->getExtra();

        if (isset($extra['branch-alias']) && \mb_strpos($version, 'dev-') === 0) {
            $branchAliases = $extra['branch-alias'];

            if (
                (isset($branchAliases[$version]) && $alias = $branchAliases[$version]) ||
                (isset($branchAliases['dev-master']) && $alias = $branchAliases['dev-master'])
            ) {
                $version = $alias;
            }
        }

        return $version;
    }

    /**
     * Create a automatic package with the composer package data.
     *
     * @param \Composer\Package\PackageInterface $composerPackage
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    private function createAutomaticPackage(PackageInterface $composerPackage): PackageContract
    {
        $package  = new Package($composerPackage->getName(), $this->getPackageVersion($composerPackage));
        $requires = [];

        foreach ($composerPackage->getRequires() as $link) {
            $target = $link->getTarget();

            if ($target === 'php' || \mb_strpos($target, 'ext-') === 0) {
                continue;
            }

            $requires[] = $target;
        }

        \sort($requires, \SORT_STRING);

        $package->setRequires($requires);

        if (($type = $composerPackage->getType()) !== null) {
            $package->setType($type);
        }

        if (($url = $composerPackage->getSourceUrl()) !== null) {
            $package->setUrl($url);
        }

        if ($this->parentName !== null) {
            $package->setParentName($this->parentName);
        }

        $package->setConfig($composerPackage->getExtra()['automatic']);

        return $package;
    }
}
