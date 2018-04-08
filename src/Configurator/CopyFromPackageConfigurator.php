<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Configurator;

use Narrowspark\Discovery\Package;
use Symfony\Component\Filesystem\Exception\IOException;

final class CopyFromPackageConfigurator extends AbstractConfigurator
{
    /**
     * {@inheritdoc}
     */
    public function configure(Package $package): void
    {
        $this->write('Copying files');

        foreach ($package->getConfiguratorOptions('copy') as $from => $to) {
            try {
                $this->filesystem->copy(
                    $this->path->concatenate([$package->getPackagePath(), $from]),
                    $this->path->concatenate([$this->path->getWorkingDir(), $to])
                );

                $this->write(\sprintf('Created <fg=green>"%s"</>', $this->path->relativize($to)));
            } catch (IOException $exception) {
                $this->write(\sprintf(
                    '<fg=red>Failed to create "%s"</>; Error message: %s',
                    $this->path->relativize($to),
                    $exception->getMessage()
                ));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(Package $package): void
    {
        $this->write('Removing files');

        foreach ($package->getConfiguratorOptions('copy') as $source) {
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
