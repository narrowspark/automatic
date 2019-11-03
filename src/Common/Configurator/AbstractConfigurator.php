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

namespace Narrowspark\Automatic\Common\Configurator;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Path;
use Narrowspark\Automatic\Common\Traits\ExpandTargetDirTrait;
use Symfony\Component\Filesystem\Filesystem;
use function file_get_contents;
use function is_array;
use function is_file;
use function rtrim;
use function sprintf;
use function strpos;
use function substr;

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
     *
     * @param \Composer\Composer       $composer
     * @param \Composer\IO\IOInterface $io
     * @param array                    $options
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
     *
     * @return void
     */
    protected function write($messages): void
    {
        if (! is_array($messages)) {
            $messages = [$messages];
        }

        foreach ($messages as $i => $message) {
            $messages[$i] = '    - ' . $message;
        }

        $this->io->writeError($messages, true, IOInterface::VERBOSE);
    }

    /**
     * Check if file is marked.
     *
     * @param string $packageName
     * @param string $file
     *
     * @return bool
     */
    protected function isFileMarked(string $packageName, string $file): bool
    {
        return is_file($file) && strpos((string) file_get_contents($file), sprintf('###> %s ###', $packageName)) !== false;
    }

    /**
     * Mark file with given data.
     *
     * @param string $packageName
     * @param string $data
     * @param int    $spaceMultiplier
     *
     * @return string
     */
    protected function markData(string $packageName, string $data, int $spaceMultiplier = 0): string
    {
        return sprintf('###> %s ###' . "\n" . '%s' . "\n" . '###< %s ###' . "\n", $packageName, rtrim($data, "\r\n"), $packageName);
    }

    /**
     * @codeCoverageIgnore
     *
     * Insert string before specified position.
     *
     * @param string $string
     * @param string $insertStr
     * @param int    $position
     *
     * @return string
     */
    protected function doInsertStringBeforePosition(string $string, string $insertStr, int $position): string
    {
        return substr($string, 0, $position) . $insertStr . substr($string, $position);
    }
}
