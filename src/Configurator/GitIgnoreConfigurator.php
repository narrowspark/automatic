<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Configurator;

use Narrowspark\Discovery\Common\Configurator\AbstractConfigurator;
use Narrowspark\Discovery\Common\Contract\Package as PackageContract;

final class GitIgnoreConfigurator extends AbstractConfigurator
{
    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
        $this->write('Added entries to .gitignore.');

        $gitignore = getcwd() . '/.gitignore';

        if ($this->isFileMarked($package->getName(), $gitignore)) {
            return;
        }

        $data = '';

        foreach ($package->getConfiguratorOptions('gitignore') as $value) {
            $value = $this->expandTargetDir($this->options, $value);
            $data .= "$value\n";
        }

        \file_put_contents($gitignore, "\n" . \ltrim($this->markData($package->getName(), $data), "\r\n"), \FILE_APPEND);
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
        $file = getcwd() . '/.gitignore';

        // @codeCoverageIgnoreStart
        if (! \file_exists($file)) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $count    = 0;
        $contents = \preg_replace(
            \sprintf('{%s*###> %s ###.*###< %s ###%s+}s', "\n", $package->getName(), $package->getName(), "\n"),
            "\n",
            \file_get_contents($file),
            -1,
            $count
        );

        if (empty($count)) {
            return;
        }

        $this->write('Removed entries in .gitignore.');

        \file_put_contents($file, \ltrim($contents, "\r\n"));
    }
}
