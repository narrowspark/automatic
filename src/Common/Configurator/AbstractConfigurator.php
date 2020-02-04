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

namespace Narrowspark\Automatic\Common\Configurator;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Path;
use Narrowspark\Automatic\Common\Traits\ExpandTargetDirTrait;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractConfigurator implements ConfiguratorContract
{
    use ExpandTargetDirTrait;

    /**
     * The composer instance.
     *
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * The composer extra options data.
     *
     * @var array
     */
    protected $options;

    /**
     * A Filesystem instance.
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * A Path instance.
     *
     * @var \Narrowspark\Automatic\Common\Path
     */
    protected $path;

    /**
     * Create a new configurator instance.
     */
    public function __construct(Composer $composer, IOInterface $io, array $options = [])
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->options = $options;
        $this->path = new Path($options['root-dir'] ?? getcwd());
        $this->filesystem = new Filesystem();
    }

    /**
     * @param array|string $messages
     */
    protected function write($messages): void
    {
        if (! \is_array($messages)) {
            $messages = [$messages];
        }

        foreach ($messages as $i => $message) {
            $messages[$i] = '    - ' . $message;
        }

        $this->io->writeError($messages, true, IOInterface::VERBOSE);
    }

    /**
     * Check if file is marked.
     */
    protected function isFileMarked(string $packageName, string $file): bool
    {
        return \is_file($file) && \strpos((string) \file_get_contents($file), \sprintf('###> %s ###', $packageName)) !== false;
    }

    /**
     * Mark file with given data.
     */
    protected function markData(string $packageName, string $data, int $spaceMultiplier = 0): string
    {
        return \sprintf("###> %s ###\n%s\n###< %s ###\n", $packageName, \rtrim($data, "\r\n"), $packageName);
    }

    /**
     * @codeCoverageIgnore
     *
     * Insert string before specified position.
     */
    protected function doInsertStringBeforePosition(string $string, string $insertStr, int $position): string
    {
        return \substr($string, 0, $position) . $insertStr . \substr($string, $position);
    }
}
