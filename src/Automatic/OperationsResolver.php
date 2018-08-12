<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Package;

final class OperationsResolver
{
    /**
     * A lock instance.
     *
     * @var \Narrowspark\Automatic\Lock
     */
    private $lock;

    /**
     * The composer vendor dir.
     *
     * @var string
     */
    private $vendorDir;

    /**
     * Create a new OperationsResolver instance.
     *
     * @param \Narrowspark\Automatic\Lock $lock
     * @param string                      $vendorDir
     */
    public function __construct(Lock $lock, string $vendorDir)
    {
        $this->lock      = $lock;
        $this->vendorDir = $vendorDir;
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

            $name          = $composerPackage->getName();
            $automaticFile = $this->vendorDir . \DIRECTORY_SEPARATOR . $name . \DIRECTORY_SEPARATOR . 'automatic.json';

            if (! \file_exists($automaticFile) && ! isset($composerPackage->getExtra()['automatic'])) {
                continue;
            }

            if ($operation instanceof UninstallOperation && $this->lock->has(Automatic::LOCK_PACKAGES, $name)) {
                $package = Package::createFromLock($name, (array) $this->lock->get(Automatic::LOCK_PACKAGES, $name));
            } else {
                $package = $this->createAutomaticPackage($composerPackage, $automaticFile);
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
     * @param string                             $automaticFile
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    private function createAutomaticPackage(PackageInterface $composerPackage, string $automaticFile): PackageContract
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

        /** @var null|string $type */
        $type = $composerPackage->getType();

        if ($type !== null) {
            $package->setType($type);
        }

        /** @var null|string $url */
        $url = $composerPackage->getSourceUrl();

        if ($url !== null) {
            $package->setUrl($url);
        }

        if (\file_exists($automaticFile)) {
            $package->setConfig(JsonFile::parseJson((string) \file_get_contents($automaticFile)));
        } else {
            $package->setConfig($composerPackage->getExtra()['automatic']);
        }

        return $package;
    }
}
