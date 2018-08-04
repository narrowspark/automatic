<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Contract\Generator\DefaultGenerator as DefaultGeneratorContract;
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
     * Create a new SkeletonGenerator instance.
     *
     * @param array                    $options
     * @param string[]                 $generators
     * @param \Composer\IO\IOInterface $io
     */
    public function __construct(array $options, array $generators, IOInterface $io)
    {
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
        $generators       = [];
        $defaultGenerator = null;

        foreach ($this->generators as $generator) {
            $type = $generator->getSkeletonType();

            if ($generator instanceof DefaultGeneratorContract) {
                $defaultGenerator = $type;
            }

            $generatorTypes[]  = $type;
            $generators[$type] = $generator;
        }

        if ($defaultGenerator === null) {
            $defaultGenerator = $generatorTypes[0];
        }

        $answer = $this->io->select('Please select a skeleton', $generatorTypes, $defaultGenerator);

        /** @var \Narrowspark\Automatic\Common\Generator\AbstractGenerator $generator */
        $generator = $generators[$answer];

        foreach ($generator->getDependencies() as $dependency => $version) {

        }

        foreach ($generator->getDevDependencies() as $dependency => $version) {

        }

        $generator->generate();
    }
}
