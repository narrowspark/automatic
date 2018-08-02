<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Package\PackageInterface;
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
     * The composer vendor path.
     *
     * @var string
     */
    private $vendorPath;

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
     * @param string                      $vendorDir
     */
    public function __construct(Lock $lock, string $vendorDir)
    {
        $this->lock       = $lock;
        $this->vendorPath = $vendorDir;
    }

    /**
     * Set the parent package name.
     * This is used for the "extra-dependency-of" key.
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
            $o = 'install';

            if ($operation instanceof UpdateOperation) {
                $package = $operation->getTargetPackage();
                $o       = 'update';
            } else {
                if ($operation instanceof UninstallOperation) {
                    $o = 'uninstall';
                }

                $package = $operation->getPackage();
            }

            if (! isset($package->getExtra()['automatic'])) {
                continue;
            }

            $name = \mb_strtolower($package->getName());

            if ($operation instanceof UninstallOperation && $this->lock->has($name)) {
                $packageConfiguration              = (array) $this->lock->get($name);
                $packageConfiguration['operation'] = $o;
            } else {
                $packageConfiguration = $this->buildPackageConfiguration($package, $o);
            }

            $packages[$name] = new Package($name, $this->vendorPath, $packageConfiguration);
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
     * Get found automatic configuration from packages.
     *
     * @param \Composer\Package\PackageInterface $package
     * @param string                             $operation
     *
     * @return array
     */
    private function buildPackageConfiguration(PackageInterface $package, string $operation): array
    {
        $requires = [];

        foreach ($package->getRequires() as $link) {
            $target = $link->getTarget();

            if ($target === 'php' || \mb_strpos($target, 'ext-') === 0) {
                continue;
            }

            $requires[] = $target;
        }

        \sort($requires, \SORT_STRING);

        return \array_merge(
            [
                'version'             => $this->getPackageVersion($package),
                'url'                 => $package->getSourceUrl(),
                'type'                => $package->getType(),
                'operation'           => $operation,
                'extra-dependency-of' => $this->parentName,
                'require'             => $requires,
            ],
            $package->getExtra()['automatic'] ?? []
        );
    }
}
