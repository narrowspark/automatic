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

final class EnvConfigurator extends AbstractConfigurator
{
    use AppendToFileTrait;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'env';
    }

    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
        $this->write('Added environment variable defaults');

        $distenv = $this->path->getWorkingDir() . \DIRECTORY_SEPARATOR . '.env.dist';

        if (! \is_file($distenv) || $this->isFileMarked($package->getPrettyName(), $distenv)) {
            return;
        }

        $data = '';

        foreach ((array) $package->getConfig(ConfiguratorContract::TYPE, self::getName()) as $key => $value) {
            if (\strpos($key, '#') === 0 && \is_numeric(\substr($key, 1))) {
                $data .= '# ' . $value . "\n";

                continue;
            }

            if (\is_string($value)) {
                $value = self::expandTargetDir($this->options, $value);
            } elseif (\filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) !== null) {
                $value = \var_export($value, true);
            }

            if (\strpbrk($value, " \t\n&!\"") !== false) {
                $value = '"' . \str_replace(['\\', '"', "\t", "\n"], ['\\\\', '\\"', '\t', '\n'], $value) . '"';
            }

            $data .= $key . '=' . $value . "\n";
        }

        if (! $this->filesystem->exists($this->path->getWorkingDir() . \DIRECTORY_SEPARATOR . '.env')) {
            $this->filesystem->copy($distenv, $this->path->getWorkingDir() . \DIRECTORY_SEPARATOR . '.env');
        }

        $data = $this->markData($package->getPrettyName(), $data);

        $this->appendToFile($distenv, $data);
        $this->appendToFile($this->path->getWorkingDir() . \DIRECTORY_SEPARATOR . '.env', $data);
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
        $this->write('Remove environment variables');

        foreach (['.env', '.env.dist'] as $file) {
            $env = $this->path->getWorkingDir() . \DIRECTORY_SEPARATOR . $file;

            // @codeCoverageIgnoreStart
            if (! $this->filesystem->exists($env)) {
                continue;
            }
            // @codeCoverageIgnoreEnd
            $count = 0;
            $contents = \preg_replace(
                \sprintf('{###> %s ###.*###< %s ###%s+}s', $package->getPrettyName(), $package->getPrettyName(), "\n"),
                '',
                (string) \file_get_contents($env),
                -1,
                $count
            );

            // @codeCoverageIgnoreStart
            if ($count === 0) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $this->write(\sprintf('Removing environment variables from %s', $file));

            $this->filesystem->dumpFile($env, (string) $contents);
        }
    }
}
