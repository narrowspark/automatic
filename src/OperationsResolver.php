<?php
declare(strict_types=1);
namespace Narrowspark\Discovery;

use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Package\PackageInterface;

class OperationsResolver
{
    /**
     * All composer operations.
     *
     * @var \Composer\DependencyResolver\Operation\OperationInterface[]
     */
    private $operations;

    /**
     * The composer vendor path.
     *
     * @var string
     */
    private $vendorDir;

    /**
     * Name of the parent package
     *
     * @var string
     */
    private $parentName;

    /**
     * Create a new OperationsResolver instance.
     *
     * @param \Composer\DependencyResolver\Operation\OperationInterface[] $operations
     * @param string                                                      $vendorDir
     */
    public function __construct(array $operations, string $vendorDir)
    {
        $this->operations = $operations;
        $this->vendorDir  = $vendorDir;
    }

    /**
     * Set the parent package name.
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
     * @return \Narrowspark\Discovery\Package[]
     */
    public function resolve(): array
    {
        $packages = [];

        foreach ($this->operations as $i => $operation) {
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

            if (! isset($package->getExtra()['discovery'])) {
                continue;
            }

            $packages[$package->getName()] = new Package(
                $package->getName(),
                $this->vendorDir,
                $this->buildPackageConfiguration($package, $o)
            );
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

        if (\mb_strpos($version, 'dev-') === 0 && isset($extra['branch-alias'])) {
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
     * Get found discovery configuration from packages.
     *
     * @param \Composer\Package\PackageInterface $package
     * @param string                             $operation
     *
     * @return array
     */
    private function buildPackageConfiguration(PackageInterface $package, string $operation): array
    {
        return \array_merge(
            [
                'version'             => $this->getPackageVersion($package),
                'url'                 => $package->getSourceUrl(),
                'type'                => $package->getType(),
                'operation'           => $operation,
                'extra-dependency-of' => $this->parentName,
            ],
            $package->getExtra()['discovery']
        );
    }
}
