<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Configurator;

use Narrowspark\Automatic\Common\Configurator\AbstractConfigurator;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;

final class EnvConfigurator extends AbstractConfigurator
{
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

        $distenv = getcwd() . '/.env.dist';

        if (! \is_file($distenv) || $this->isFileMarked($package->getName(), $distenv)) {
            return;
        }

        $data = '';

        foreach ($package->getConfiguratorOptions('env') as $key => $value) {
            if ($key[0] === '#' && \is_numeric(\mb_substr($key, 1))) {
                $data .= '# ' . $value . "\n";

                continue;
            }

            $value = self::expandTargetDir($this->options, $value);

            if (\strpbrk($value, " \t\n&!\"") !== false) {
                $value = '"' . \str_replace(['\\', '"', "\t", "\n"], ['\\\\', '\\"', '\t', '\n'], $value) . '"';
            }

            $data .= "${key}=${value}\n";
        }

        if (! \file_exists(getcwd() . '/.env')) {
            \copy($distenv, getcwd() . '/.env');
        }

        $data = $this->markData($package->getName(), $data);

        \file_put_contents($distenv, $data, \FILE_APPEND);
        \file_put_contents(getcwd() . '/.env', $data, \FILE_APPEND);
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
        $this->write('Remove environment variables');

        foreach (['.env', '.env.dist'] as $file) {
            $env = getcwd() . '/' . $file;

            // @codeCoverageIgnoreStart
            if (! \file_exists($env)) {
                continue;
            }
            /** @codeCoverageIgnoreEnd */
            $count    = 0;
            $contents = \preg_replace(
                \sprintf('{%s*###> %s ###.*###< %s ###%s+}s', "\n", $package->getName(), $package->getName(), "\n"),
                '',
                (string) \file_get_contents($env),
                -1,
                $count
            );

            // @codeCoverageIgnoreStart
            if (empty($count)) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $this->write(\sprintf('Removing environment variables from %s', $file));

            \file_put_contents($env, $contents);
        }
    }
}
