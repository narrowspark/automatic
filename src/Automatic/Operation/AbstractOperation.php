<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Operation;

use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\ClassFinder;
use Narrowspark\Automatic\Common\Contract\Configurator as CommonConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Contract\Operation as OperationContract;
use Narrowspark\Automatic\Contract\PackageConfigurator as PackageConfiguratorContract;
use Narrowspark\Automatic\Lock;

abstract class AbstractOperation implements OperationContract
{
    /**
     * The composer vendor dir.
     *
     * @var string
     */
    protected $vendorDir;

    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * A lock instance.
     *
     * @var \Narrowspark\Automatic\Lock
     */
    protected $lock;

    /**
     * A configurator instance.
     *
     * @var \Narrowspark\Automatic\Contract\Configurator
     */
    protected $configurator;

    /**
     * A package configurator instance.
     *
     * @var \Narrowspark\Automatic\Contract\PackageConfigurator
     */
    protected $packageConfigurator;

    /**
     * A class finder instance.
     *
     * @var \Narrowspark\Automatic\Common\ClassFinder
     */
    protected $classFinder;

    /**
     * Base functions for Install and Uninstall Operation.
     *
     * @param string                                              $vendorDir
     * @param \Narrowspark\Automatic\Lock                         $lock
     * @param \Composer\IO\IOInterface                            $io
     * @param \Narrowspark\Automatic\Contract\Configurator        $configurator
     * @param \Narrowspark\Automatic\Contract\PackageConfigurator $packageConfigurator
     * @param \Narrowspark\Automatic\Common\ClassFinder           $classFinder
     */
    public function __construct(
        string $vendorDir,
        Lock $lock,
        IOInterface $io,
        ConfiguratorContract $configurator,
        PackageConfiguratorContract $packageConfigurator,
        ClassFinder $classFinder
    ) {
        $this->vendorDir           = $vendorDir;
        $this->lock                = $lock;
        $this->io                  = $io;
        $this->configurator        = $configurator;
        $this->packageConfigurator = $packageConfigurator;
        $this->classFinder         = $classFinder;
    }

    /**
     * Show a waring if remaining configurators are found in package config.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package      $package
     * @param \Narrowspark\Automatic\Contract\PackageConfigurator $packageConfigurator
     * @param \Narrowspark\Automatic\Contract\Configurator        $configurator
     *
     * @return void
     */
    protected function showWarningOnRemainingConfigurators(
        PackageContract $package,
        PackageConfiguratorContract $packageConfigurator,
        ConfiguratorContract $configurator
    ): void {
        $packageConfigurators = \array_keys((array) $package->getConfig(CommonConfiguratorContract::TYPE));

        foreach (\array_keys($configurator->getConfigurators()) as $key => $value) {
            if (isset($packageConfigurators[$key])) {
                unset($packageConfigurators[$key]);
            }
        }

        foreach (\array_keys($packageConfigurator->getConfigurators()) as $key => $value) {
            if (isset($packageConfigurators[$key])) {
                unset($packageConfigurators[$key]);
            }
        }

        if (\count($packageConfigurators) !== 0) {
            $this->io->writeError(\sprintf(
                '<warning>Configurators [%s] did not run for package [%s]</warning>',
                \implode(', ', $packageConfigurators),
                $package->getPrettyName()
            ));
        }
    }

    /**
     * Add package configuration from package.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     *
     * @throws \ReflectionException
     *
     * @return void
     */
    protected function addPackageConfigurators(PackageContract $package): void
    {
        if ($package->hasConfig(PackageConfiguratorContract::TYPE)) {
            /** @var \Narrowspark\Automatic\Common\Configurator\AbstractConfigurator $class */
            foreach ((array) $package->getConfig(PackageConfiguratorContract::TYPE) as $class) {
                $reflectionClass = new \ReflectionClass($class);

                if ($reflectionClass->isInstantiable() && $reflectionClass->hasMethod('getName')) {
                    $this->packageConfigurator->add($class::getName(), $reflectionClass->getName());
                }
            }
        }
    }

    /**
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     * @param string                                         $name
     *
     * @return array
     */
    protected function findClassesInAutomaticFolder(PackageContract $package, string $name): array
    {
        $composerAutoload = $package->getAutoload();
        $classes          = [];

        if (\count($composerAutoload) !== 0) {
            $classes = $this->classFinder->setComposerAutoload($name, $composerAutoload)
                ->setFilter(function (\SplFileInfo $fileInfo) use ($name) {
                    return \mb_strpos((string) \mb_strstr($fileInfo->getPathname(), $name), \DIRECTORY_SEPARATOR . 'Automatic' . \DIRECTORY_SEPARATOR) !== false;
                })
                ->find()
                ->getAll();
        }

        return $classes;
    }
}
