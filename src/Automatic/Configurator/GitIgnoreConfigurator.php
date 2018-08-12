<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Configurator;

use Narrowspark\Automatic\Common\Configurator\AbstractConfigurator;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;

final class GitIgnoreConfigurator extends AbstractConfigurator
{
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
        $this->write('Added entries to .gitignore.');

        $gitignore = $this->path->getWorkingDir() . \DIRECTORY_SEPARATOR . '.gitignore';

        if ($this->isFileMarked($package->getPrettyName(), $gitignore)) {
            return;
        }

        $data = '';

        foreach ((array) $package->getConfig(self::getName()) as $value) {
            $value = self::expandTargetDir($this->options, $value);
            $data .= "${value}\n";
        }

        \file_put_contents($gitignore, "\n" . \ltrim($this->markData($package->getPrettyName(), $data), "\r\n"), \FILE_APPEND);
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
        $file = $this->path->getWorkingDir() . \DIRECTORY_SEPARATOR . '.gitignore';

        /** @codeCoverageIgnoreStart */
        if (! \file_exists($file)) {
            return;
        }
        /** @codeCoverageIgnoreEnd */
        $count    = 0;
        $contents = \preg_replace(
            \sprintf('{%s*###> %s ###.*###< %s ###%s+}s', "\n", $package->getPrettyName(), $package->getPrettyName(), "\n"),
            "\n",
            (string) \file_get_contents($file),
            -1,
            $count
        );

        if ($count === 0) {
            return;
        }

        $this->write('Removed entries in .gitignore.');

        \file_put_contents($file, \ltrim($contents, "\r\n"));
    }
}
