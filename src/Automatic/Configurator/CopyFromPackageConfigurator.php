<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Configurator;

use Narrowspark\Automatic\Common\Configurator\AbstractConfigurator;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Symfony\Component\Filesystem\Exception\IOException;

final class CopyFromPackageConfigurator extends AbstractConfigurator
{
    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'copy';
    }

    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
        $this->write('Copying files');

        foreach ((array) $package->getConfig(ConfiguratorContract::TYPE, self::getName()) as $from => $to) {
            $target = self::expandTargetDir($this->options, $to);
            $from   = $this->path->concatenate([$this->composer->getConfig()->get('vendor-dir') . \DIRECTORY_SEPARATOR . $package->getPrettyName() . \DIRECTORY_SEPARATOR, $from]);

            if (! \is_dir($from) && ! \is_file($from)) {
                $this->write(\sprintf(
                    '<fg=red>Failed to find the from folder or file path for "%s" in "%s" package</>',
                    $this->path->relativize($from),
                    $package->getName()
                ));

                return;
            }

            try {
                $functionName = 'copy';

                if (\is_dir($from)) {
                    $functionName = 'mirror';
                }

                $this->filesystem->{$functionName}(
                    $from,
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

        foreach ((array) $package->getConfig(ConfiguratorContract::TYPE, self::getName()) as $source) {
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
