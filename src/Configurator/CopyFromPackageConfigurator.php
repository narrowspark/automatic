<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Configurator;

use Narrowspark\Discovery\Common\Configurator\AbstractConfigurator;
use Narrowspark\Discovery\Common\Contract\Package as PackageContract;
use Symfony\Component\Filesystem\Exception\IOException;

final class CopyFromPackageConfigurator extends AbstractConfigurator
{
    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
        $this->write('Copying files');

        foreach ($package->getConfiguratorOptions('copy') as $from => $to) {
            $target = self::expandTargetDir($this->options, $to);

            try {
                $this->filesystem->copy(
                    $this->path->concatenate([$package->getPackagePath(), $from]),
                    $this->path->concatenate([$this->path->getWorkingDir(), $target])
                );

                $this->write(\sprintf('Created <fg=green>"%s"</>', $this->path->relativize($target)));
            } catch (IOException $exception) {
                $this->write(\sprintf(
                    '<fg=red>Failed to create "%s"</>; Error message: %s',
                    $this->path->relativize($target),
                    $exception->getMessage()
                ));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
        $this->write('Removing files');

        foreach ($package->getConfiguratorOptions('copy') as $source) {
            $source = self::expandTargetDir($this->options, $source);

            try {
                $this->filesystem->remove($this->path->concatenate([$this->path->getWorkingDir(), $source]));

                $this->write(\sprintf('Removed <fg=green>"%s"</>', $this->path->relativize($source)));
            } catch (IOException $exception) {
                $this->write(\sprintf(
                    '<fg=red>Failed to remove "%s"</>; Error message: %s',
                    $this->path->relativize($source),
                    $exception->getMessage()
                ));
            }
        }
    }
}
