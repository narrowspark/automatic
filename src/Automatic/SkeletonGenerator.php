<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Composer\IO\IOInterface;
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
     * @param \Composer\IO\IOInterface                             $io
     * @param \Narrowspark\Automatic\Installer\InstallationManager $installationManager
     * @param \Narrowspark\Automatic\Lock                          $lock
     * @param string                                               $vendorPath
     * @param string[]                                             $options
     */
    public function __construct(
        IOInterface $io,
        InstallationManager $installationManager,
        Lock $lock,
        string $vendorPath,
        array $options
    ) {
        $this->io                   = $io;
        $this->installationManager  = $installationManager;
        $this->lock                 = $lock;
        $this->options              = $options;
        $this->vendorPath           = $vendorPath;
    }

    /**
     * Generate the selected skeleton.
     *
     * @throws \Exception
     *
     * @return \Narrowspark\Automatic\SkeletonGenerator
     */
    public function run(): self
    {
        $generators = $this->prepareGenerators();

        $generatorTypes       = [];
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

        $this->installationManager->install(
            $this->transformToPackages($generator->getDependencies()),
            $this->transformToPackages($generator->getDevDependencies())
        );

        $this->installationManager->run();

        $generator->generate();

        return $this;
    }

    /**
     * Removes all information about the skeleton package.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function selfRemove(): void
    {
        $requires = [];

        foreach ((array) $this->lock->get(SkeletonInstaller::LOCK_KEY) as $name => $generators) {
            $requires[] = new Package($name, null);
        }

        $this->installationManager->uninstall($requires, []);

        $classmap = $this->lock->get(Automatic::LOCK_CLASSMAP);

        /** @var \Narrowspark\Automatic\Common\Contract\Package $package */
        foreach ($requires as $package) {
            unset($classmap[$package->getName()]);
        }

        $this->lock->add(Automatic::LOCK_CLASSMAP, $classmap);
        $this->lock->remove(SkeletonInstaller::LOCK_KEY);

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
            foreach ($classMap[$name] as $class => $path) {
                if (! \class_exists($class)) {
                    require_once \str_replace('%vendor_path%', $this->vendorPath, $path);
                }
            }

            $foundGenerators += $generators;
        }

        $options = $this->options;

        \array_walk($foundGenerators, static function (&$class) use ($options) {
            /** @var \Narrowspark\Automatic\Common\Generator\AbstractGenerator $class */
            $class = new $class(new Filesystem(), $options);
        });

        return $foundGenerators;
    }

    /**
     * Transforms requires data to automatic packages.
     *
     * @param array $requires
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
