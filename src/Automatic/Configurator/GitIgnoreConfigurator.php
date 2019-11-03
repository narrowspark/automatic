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

namespace Narrowspark\Automatic\Configurator;

use Narrowspark\Automatic\Common\Configurator\AbstractConfigurator;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Configurator\Traits\AppendToFileTrait;
use const DIRECTORY_SEPARATOR;
use function file_get_contents;
use function ltrim;
use function preg_replace;
use function sprintf;

final class GitIgnoreConfigurator extends AbstractConfigurator
{
    use AppendToFileTrait;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'gitignore';
    }

    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
        $this->write('Added entries to .gitignore');

        $gitignore = $this->path->getWorkingDir() . DIRECTORY_SEPARATOR . '.gitignore';

        if ($this->isFileMarked($package->getPrettyName(), $gitignore)) {
            return;
        }

        $data = '';

        foreach ((array) $package->getConfig(ConfiguratorContract::TYPE, self::getName()) as $value) {
            $value = self::expandTargetDir($this->options, $value);
            $data .= $value . "\n";
        }

        $this->appendToFile($gitignore, "\n" . ltrim($this->markData($package->getPrettyName(), $data), "\r\n"));
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
        $file = $this->path->getWorkingDir() . DIRECTORY_SEPARATOR . '.gitignore';

        // @codeCoverageIgnoreStart
        if (! $this->filesystem->exists($file)) {
            return;
        }
        // @codeCoverageIgnoreEnd
        $count = 0;
        $contents = preg_replace(
            sprintf('{###> %s ###.*###< %s ###%s+}s', $package->getPrettyName(), $package->getPrettyName(), "\n"),
            "\n",
            (string) file_get_contents($file),
            -1,
            $count
        );

        if ($count === 0) {
            return;
        }

        $this->write('Removed entries in .gitignore');

        $this->filesystem->dumpFile($file, ltrim((string) $contents, "\r\n"));
    }
}
