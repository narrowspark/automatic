<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Operation;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\ScriptExecutor;

/**
 * @internal
 */
final class Install extends AbstractOperation
{
    /**
     * {@inheritdoc}
     */
    public function supports(OperationInterface $operation): bool
    {
        /** @var \Composer\DependencyResolver\Operation\InstallOperation|\Composer\DependencyResolver\Operation\UpdateOperation $operation */
        if ($operation instanceof UpdateOperation) {
            $composerPackage = $operation->getTargetPackage();
        } else {
            $composerPackage = $operation->getPackage();
        }

        return ($operation instanceof UpdateOperation || $operation instanceof InstallOperation) &&
            (\file_exists($this->getAutomaticFilePath($composerPackage)) || isset($composerPackage->getExtra()['automatic']));
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(OperationInterface $operation): PackageContract
    {
        /** @var \Composer\DependencyResolver\Operation\InstallOperation|\Composer\DependencyResolver\Operation\UpdateOperation $operation */
        if ($operation instanceof UpdateOperation) {
            $composerPackage = $operation->getTargetPackage();
            $package         = $this->createAutomaticPackage($composerPackage, $this->getAutomaticFilePath($composerPackage));
            $package->setOperation(PackageContract::UPDATE_OPERATION);

            return $package;
        }

        $composerPackage = $operation->getPackage();

        $package = $this->createAutomaticPackage($composerPackage, $this->getAutomaticFilePath($composerPackage));
        $package->setOperation(PackageContract::INSTALL_OPERATION);

        return $package;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(PackageContract $package): void
    {
        $name = $package->getName();

        $classes = $this->findClassesInAutomaticFolder($package, $name);

        foreach ($classes as $class => $path) {
            if (! \class_exists($class)) {
                require_once $path;
            }
        }

        $this->addScriptExtenders($package, $classes, $name);

        $this->configurator->configure($package);

        $this->addPackageConfigurators($package);

        $this->packageConfigurator->configure($package);

        $this->showWarningOnRemainingConfigurators($package, $this->packageConfigurator, $this->configurator);

        $this->lock->addSub(Automatic::LOCK_PACKAGES, $package->getName(), $package->toArray());

        $this->packageConfigurator->reset();
        $this->classFinder->reset();
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

        if (isset($extra['branch-alias']) && \strpos($version, 'dev-') === 0) {
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
     * @throws \Exception
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    private function createAutomaticPackage(PackageInterface $composerPackage, string $automaticFile): PackageContract
    {
        $package  = new Package($composerPackage->getName(), $this->getPackageVersion($composerPackage));
        $requires = [];

        foreach ($composerPackage->getRequires() as $link) {
            $target = $link->getTarget();

            if ($target === 'php' || \strpos($target, 'ext-') === 0) {
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

        $package->setAutoload($composerPackage->getAutoload());

        if (\file_exists($automaticFile)) {
            $package->setConfig(JsonFile::parseJson((string) \file_get_contents($automaticFile)));
        } else {
            $package->setConfig($composerPackage->getExtra()['automatic']);
        }

        return $package;
    }

    /**
     * Get the automatic.json file path from package.
     *
     * @param \Composer\Package\PackageInterface $composerPackage
     *
     * @return string
     */
    private function getAutomaticFilePath(PackageInterface $composerPackage): string
    {
        return $this->vendorDir . \DIRECTORY_SEPARATOR . $composerPackage->getName() . \DIRECTORY_SEPARATOR . 'automatic.json';
    }

    /**
     * Add package script executors.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     * @param array                                          $classes
     * @param string                                         $name
     *
     * @return void
     */
    private function addScriptExtenders(PackageContract $package, $classes, $name): void
    {
        if ($package->hasConfig(ScriptExecutor::TYPE) && \count($classes) !== 0) {
            $extenders         = [];
            $notFoundExtenders = [];

            foreach ((array) $package->getConfig(ScriptExecutor::TYPE) as $extender) {
                if (isset($classes[$extender])) {
                    $extenders[$extender] = $classes[$extender];
                } else {
                    $notFoundExtenders[] = '        - ' . $extender;
                }
            }

            if (\count($notFoundExtenders) !== 0) {
                $count = \count($notFoundExtenders);

                \array_unshift(
                    $notFoundExtenders,
                    \sprintf('%s script-extender%s not found in [%s]', $count, ($count <= 1 ? ' was' : 's were'), $name)
                );

                $this->io->write($notFoundExtenders, true, IOInterface::VERBOSE);
            }

            $this->lock->addSub(ScriptExecutor::TYPE, $name, $extenders);
        }
    }
}
