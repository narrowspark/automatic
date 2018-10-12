<?php
declare(strict_types=1);
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
            if (\mb_strpos($key, '#') === 0 && \is_numeric(\mb_substr($key, 1))) {
                $data .= '# ' . $value . \PHP_EOL;

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

            $data .= $key . '=' . $value . \PHP_EOL;
        }

        if (! \file_exists($this->path->getWorkingDir() . \DIRECTORY_SEPARATOR . '.env')) {
            \copy($distenv, $this->path->getWorkingDir() . \DIRECTORY_SEPARATOR . '.env');
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
            if (! \file_exists($env)) {
                continue;
            }
            /** @codeCoverageIgnoreEnd */
            $count    = 0;
            $contents = \preg_replace(
                \sprintf('{###> %s ###.*###< %s ###%s+}s', $package->getPrettyName(), $package->getPrettyName(), \PHP_EOL),
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

            $this->filesystem->dumpFile($env, $contents);
        }
    }
}
