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
     * List of skeleton packages.
     *
     * @var \Narrowspark\Automatic\Common\Contract\Package[]
     */
    private $skeletons;

    /**
     * List of composer extra options.
     *
     * @var \Narrowspark\Automatic\Common\Contract\Package[]
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
     * @param \Composer\IO\IOInterface                             $io
     * @param \Narrowspark\Automatic\Installer\InstallationManager $installationManager
     * @param \Narrowspark\Automatic\Lock                          $lock
     * @param string                                               $vendorPath
     * @param \Narrowspark\Automatic\Common\Contract\Package[]     $skeletons
     * @param string[]                                             $options
     */
    public function __construct(
        IOInterface $io,
        InstallationManager $installationManager,
        Lock $lock,
        string $vendorPath,
        array $skeletons,
        array $options
    ) {
        $this->io                   = $io;
        $this->installationManager  = $installationManager;
        $this->lock                 = $lock;
        $this->skeletons            = $skeletons;
        $this->options              = $options;
        $this->vendorPath           = $vendorPath;
    }

    /**
     * Generate the selected skeleton.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function run(): void
    {
        $generators = $this->prepareGenerators();

        $generatorTypes       = [];
        $defaultGeneratorType = null;

        /** @var \Narrowspark\Automatic\Common\Generator\AbstractGenerator $generator */
        foreach ($generators as $key => $generator) {
            $type = $generator->getSkeletonType();

            if ($defaultGeneratorType === null && $generator instanceof DefaultGeneratorContract) {
                $defaultGeneratorType = $type;

                continue;
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

        $this->installationManager->install($generator->getDependencies(), $generator->getDevDependencies());

        $this->installationManager->run();

        $generator->generate();
    }

    /**
     * Removes all information about the skeleton package.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function remove(): void
    {
        $this->lock->remove(SkeletonInstaller::LOCK_KEY);

        $requires = [];
        $names    = [];

        foreach ($this->skeletons as $package) {
            $names[]    = $package->getName();
            $requires[] = $package;
        }

        $this->installationManager->uninstall($requires, []);

        $classmap = $this->lock->get(Automatic::LOCK_CLASSMAP);

        foreach ($names as $name) {
            unset($classmap[$name]);
        }

        $this->lock->add(Automatic::LOCK_CLASSMAP, $classmap);

        $this->lock->write();
    }

    /**
     * Requires the found skeleton generators and create them.
     *
     * @return \Narrowspark\Automatic\Common\Generator\AbstractGenerator[]
     */
    private function prepareGenerators(): array
    {
        $foundGenerators = [];
        $classMap        = (array) $this->lock->get(Automatic::LOCK_CLASSMAP);

        foreach ((array) $this->lock->get(SkeletonInstaller::LOCK_KEY) as $name => $generators) {
            foreach ($classMap[$name] as $path) {
                require_once \str_replace('%vendor_path%', $this->vendorPath, $path);
            }

            $foundGenerators += $generators;
        }

        \array_walk($foundGenerators, static function (&$class) {
            /** @var \Narrowspark\Automatic\Common\Generator\AbstractGenerator $class */
            $class = new $class(new Filesystem(), $this->options);
        });

        return $foundGenerators;
    }
}
