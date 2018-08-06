<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Contract\Generator\DefaultGenerator as DefaultGeneratorContract;
use Narrowspark\Automatic\Installer\InstallationManager;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Symfony\Component\Filesystem\Filesystem;

final class SkeletonGenerator
{
    /**
     * The skeleton generators.
     *
     * @var \Narrowspark\Automatic\Common\Generator\AbstractGenerator[]
     */
    private $generators;

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
     * Create a new SkeletonGenerator instance.
     *
     * @param \Composer\IO\IOInterface                             $io
     * @param \Narrowspark\Automatic\Installer\InstallationManager $installationManager
     * @param string[]                                             $options
     * @param string[]                                             $generators
     */
    public function __construct(
        IOInterface $io,
        InstallationManager $installationManager,
        array $options,
        array $generators
    ) {
        \array_walk($generators, static function (&$class) use ($options) {
            /** @var \Narrowspark\Automatic\Common\Generator\AbstractGenerator $class */
            $class = new $class(new Filesystem(), $options);
        });

        $this->generators          = $generators;
        $this->io                  = $io;
        $this->installationManager = $installationManager;
    }

    /**
     * Generate the project.
     *
     * @return void
     */
    public function run(): void
    {
        $generatorTypes   = [];
        $defaultGenerator = null;

        foreach ($this->generators as $key => $generator) {
            $type = $generator->getSkeletonType();

            if ($generator instanceof DefaultGeneratorContract) {
                $defaultGenerator = $type;
            }

            $generatorTypes[$key] = $type;
        }

        if ($defaultGenerator === null) {
            $defaultGenerator = $generatorTypes[0];
        }

        /** @var int $answer */
        $answer = $this->io->select('Please select a skeleton:', $generatorTypes, $defaultGenerator);

        /** @var \Narrowspark\Automatic\Common\Generator\AbstractGenerator $generator */
        $generator = $this->generators[$answer];

        $this->io->write(\sprintf('%sGenerating [%s] skeleton.%s', "\n", $generatorTypes[$answer], "\n"));

        $this->installationManager->install($generator->getDependencies(), $generator->getDevDependencies());

        $generator->generate();
    }

    /**
     * Removes all information about the skeleton package.
     *
     * @param \Narrowspark\Automatic\Lock $lock
     * @param array                       $package
     *
     * @throws \Exception
     *
     * @return void
     */
    public function remove(Lock $lock, array $package): void
    {
        $lock->remove(SkeletonInstaller::LOCK_KEY);

        $this->installationManager->uninstall($package, []);

        $classmap = $lock->get(Automatic::LOCK_CLASSMAP);

        unset($classmap[\key($package)]);

        $lock->add(Automatic::LOCK_CLASSMAP, $classmap);

        $lock->write();
    }
}
