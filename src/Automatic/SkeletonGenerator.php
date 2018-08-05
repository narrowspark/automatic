<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Composer\IO\IOInterface;
use Composer\Json\JsonManipulator;
use Narrowspark\Automatic\Common\Contract\Generator\DefaultGenerator as DefaultGeneratorContract;
use Narrowspark\Automatic\Common\Util;
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
     * The skeleton package name.
     *
     * @var string
     */
    private $packageName;

    /**
     * Create a new SkeletonGenerator instance.
     *
     * @param array                    $options
     * @param string[]                 $generators
     * @param \Composer\IO\IOInterface $io
     */
    public function __construct(array $options, array $generators, IOInterface $io)
    {
        $this->packageName = (string) \key($generators);

        $generators = Util::flattenArray(\array_values($generators));

        \array_walk($generators, static function (&$class) use ($options) {
            /** @var \Narrowspark\Automatic\Common\Generator\AbstractGenerator $class */
            $class = new $class(new Filesystem(), $options);
        });

        $this->generators = $generators;
        $this->io         = $io;
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

        foreach ($generator->getDependencies() as $dependency => $version) {
        }

        foreach ($generator->getDevDependencies() as $dependency => $version) {
        }

        $generator->generate();
    }

    /**
     * Removes all information about the skeleton package.
     *
     * @param \Composer\Json\JsonManipulator $manipulator
     * @param \Narrowspark\Automatic\Lock    $lock
     *
     * @throws \Exception
     *
     * @return void
     */
    public function remove(JsonManipulator $manipulator, Lock $lock): void
    {
        $manipulator->removeSubNode('require', $this->packageName);
        $manipulator->removeSubNode('require-dev', $this->packageName);

        $lock->remove(SkeletonInstaller::LOCK_KEY);
        $lock->remove(SkeletonInstaller::LOCK_KEY_CLASSMAP);
        $lock->write();
    }
}
