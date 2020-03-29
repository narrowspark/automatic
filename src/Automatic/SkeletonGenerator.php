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

namespace Narrowspark\Automatic;

use Composer\IO\IOInterface;
use Exception;
use Narrowspark\Automatic\Common\Contract\Generator\DefaultGenerator as DefaultGeneratorContract;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\Installer\InstallationManager;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Symfony\Component\Filesystem\Filesystem;

final class SkeletonGenerator
{
    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * A InstallationManager instance.
     *
     * @var \Narrowspark\Automatic\Installer\InstallationManager
     */
    private $installationManager;

    /**
     * A lock instance.
     *
     * @var \Narrowspark\Automatic\Lock
     */
    private $lock;

    /**
     * List of composer extra options.
     *
     * @var string[]
     */
    private $options;

    /**
     * The composer vendor path.
     *
     * @var string
     */
    private $vendorPath;

    /**
     * Create a new SkeletonGenerator instance.
     *
     * @param \Narrowspark\Automatic\Lock $lock
     * @param string[]                    $options
     */
    public function __construct(
        IOInterface $io,
        InstallationManager $installationManager,
        Lock $lock,
        string $vendorPath,
        array $options
    ) {
        $this->io = $io;
        $this->installationManager = $installationManager;
        $this->lock = $lock;
        $this->options = $options;
        $this->vendorPath = $vendorPath;
    }

    /**
     * Generate the selected skeleton.
     *
     * @throws Exception
     */
    public function run(): void
    {
        $generators = $this->prepareGenerators();

        $generatorTypes = [];
        $defaultGeneratorType = null;

        /** @var \Narrowspark\Automatic\Common\Generator\AbstractGenerator $generator */
        foreach ($generators as $key => $generator) {
            $type = $generator->getSkeletonType();

            if ($defaultGeneratorType === null && $generator instanceof DefaultGeneratorContract) {
                $defaultGeneratorType = $type;
            }

            $generatorTypes[$key] = $type;
        }

        if ($defaultGeneratorType === null) {
            $defaultGeneratorType = $generatorTypes[0];
        }

        /** @var int $answer */
        $answer = $this->io->select('Please select a skeleton:', $generatorTypes, $defaultGeneratorType);

        /** @var \Narrowspark\Automatic\Common\Generator\AbstractGenerator $generator */
        $generator = $generators[$answer];

        $this->io->write(\sprintf('%sGenerating [%s] skeleton.%s', "\n", $generatorTypes[$answer], "\n"));

        $generator->generate();

        $this->installationManager->install(
            $this->transformToPackages($generator->getDependencies()),
            $this->transformToPackages($generator->getDevDependencies())
        );

        $status = $this->installationManager->run();

        // @codeCoverageIgnoreStart
        if ($status !== 0) {
            exit($status);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Removes all information about the skeleton package.
     *
     * @throws Exception
     */
    public function selfRemove(): void
    {
        $this->lock->read();

        $requires = [];

        foreach ((array) $this->lock->get(SkeletonInstaller::LOCK_KEY) as $name => $generators) {
            $requires[] = new Package($name, null);

            $this->lock->remove(Automatic::LOCK_CLASSMAP, $name);
        }

        $this->installationManager->uninstall($requires, []);

        $this->lock->remove(SkeletonInstaller::LOCK_KEY);
        $this->lock->write();
    }

    /**
     * Requires the found skeleton generators and create them.
     *
     * @return float|int|mixed[]
     */
    private function prepareGenerators(): array
    {
        $foundGenerators = [];

        foreach ((array) $this->lock->get(SkeletonInstaller::LOCK_KEY) as $name => $generators) {
            foreach ((array) $this->lock->get(Automatic::LOCK_CLASSMAP, $name) as $class => $path) {
                if (! \class_exists($class)) {
                    require_once \str_replace('%vendor_path%', $this->vendorPath, $path);
                }
            }

            $foundGenerators += $generators;
        }

        $options = $this->options;

        \array_walk($foundGenerators, static function (string &$class) use ($options): void {
            /** @var \Narrowspark\Automatic\Common\Generator\AbstractGenerator $class */
            $class = new $class(new Filesystem(), $options);
        });

        return $foundGenerators;
    }

    /**
     * Transforms requires data to automatic packages.
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package[]
     */
    private function transformToPackages(array $requires): array
    {
        $packages = [];

        foreach ($requires as $name => $version) {
            $packages[] = new Package($name, $version);
        }

        return $packages;
    }
}
