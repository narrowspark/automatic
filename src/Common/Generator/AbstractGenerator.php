<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\Automatic\Common\Generator;

use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractGenerator
{
    /**
     * This should be only used if this class is tested.
     *
     * @internal
     *
     * @var bool
     */
    public static $isTest = false;

    /**
     * A Filesystem instance.
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * The composer extra options data.
     *
     * @var array
     */
    protected $options;

    /**
     * Basic functions for the generator classes.
     *
     * @param \Symfony\Component\Filesystem\Filesystem $filesystem
     * @param array                                    $options
     */
    public function __construct(Filesystem $filesystem, array $options)
    {
        $this->filesystem = $filesystem;
        $this->options = $options;
    }

    /**
     * Returns the skeleton type of the class.
     *
     * @return string
     */
    abstract public function getSkeletonType(): string;

    /**
     * Generate the project.
     *
     * @return void
     */
    public function generate(): void
    {
        $this->filesystem->mkdir($this->getDirectories());

        foreach ($this->getFiles() as $filePath => $fileContent) {
            $this->filesystem->dumpFile($filePath, $fileContent);
        }

        $this->filesystem->remove($this->clean());
    }

    /**
     * Returns all requirements that should be installed.
     *
     * @return string[]
     */
    abstract public function getDependencies(): array;

    /**
     * Returns all dev requirements that should be installed.
     *
     * @return string[]
     */
    abstract public function getDevDependencies(): array;

    /**
     * Returns all directories that should be generated.
     *
     * @return string[]
     */
    abstract protected function getDirectories(): array;

    /**
     * Returns all files that should be generated.
     *
     * @return array
     */
    abstract protected function getFiles(): array;

    /**
     * List of narrowspark files and directories that should be removed.
     *
     * @return array
     */
    protected function clean(): array
    {
        return [];
    }
}
