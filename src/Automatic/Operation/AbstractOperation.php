<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018-2020 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/automatic
 */

namespace Narrowspark\Automatic\Operation;

use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\ClassFinder;
use Narrowspark\Automatic\Common\Contract\Configurator as CommonConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Contract\Operation as OperationContract;
use Narrowspark\Automatic\Contract\PackageConfigurator as PackageConfiguratorContract;
use Narrowspark\Automatic\Lock;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;

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
     * A Filesystem instance.
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * Base functions for Install and Uninstall Operation.
     */
    public function __construct(
        string $vendorDir,
        Lock $lock,
        IOInterface $io,
        ConfiguratorContract $configurator,
        PackageConfiguratorContract $packageConfigurator,
        ClassFinder $classFinder
    ) {
        $this->vendorDir = $vendorDir;
        $this->lock = $lock;
        $this->io = $io;
        $this->configurator = $configurator;
        $this->packageConfigurator = $packageConfigurator;
        $this->classFinder = $classFinder;
        $this->filesystem = new Filesystem();
    }

    /**
     * Show a waring if remaining configurators are found in package config.
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
     * @throws ReflectionException
     */
    protected function addPackageConfigurators(PackageContract $package): void
    {
        if ($package->hasConfig(PackageConfiguratorContract::TYPE)) {
            /** @var \Narrowspark\Automatic\Common\Configurator\AbstractConfigurator $class */
            foreach ((array) $package->getConfig(PackageConfiguratorContract::TYPE) as $class) {
                $reflectionClass = new ReflectionClass($class);

                if ($reflectionClass->isInstantiable() && $reflectionClass->hasMethod('getName')) {
                    $this->packageConfigurator->add($class::getName(), $reflectionClass->getName());
                }
            }
        }
    }

    /**
     * @return mixed[]
     */
    protected function findClassesInAutomaticFolder(PackageContract $package, string $name): array
    {
        $composerAutoload = $package->getAutoload();
        $classes = [];

        if (\count($composerAutoload) !== 0) {
            $classes = $this->classFinder->setComposerAutoload($name, $composerAutoload)
                ->setFilter(static function (SplFileInfo $fileInfo) use ($name): bool {
                    return \strpos((string) \strstr($fileInfo->getPathname(), $name), \DIRECTORY_SEPARATOR . 'Automatic' . \DIRECTORY_SEPARATOR) !== false;
                })
                ->find()
                ->getAll();
        }

        return $classes;
    }
}
