<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Configurator;

use Narrowspark\Discovery\Common\Configurator\AbstractConfigurator;
use Narrowspark\Discovery\Common\Contract\Package as PackageContract;
use Narrowspark\Discovery\Common\Traits\PhpFileMarkerTrait;
use Narrowspark\Discovery\Configurator\Traits\GetSortedClassesTrait;

/**
 * @internal
 */
abstract class AbstractClassConfigurator extends AbstractConfigurator
{
    use PhpFileMarkerTrait;
    use GetSortedClassesTrait;

    /**
     * The composer option name.
     *
     * @var string
     */
    protected static $optionName;

    /**
     * The output configure write message.
     *
     * @var string
     */
    protected static $configureOutputMessage;

    /**
     * The output unconfigure write message.
     *
     * @var string
     */
    protected static $unconfigureOutputMessage;

    /**
     * The config file name.
     *
     * @var string
     */
    protected static $configFileName;

    /**
     * Configure the space repeat.
     *
     * @var int
     */
    protected static $spaceMultiplication = 4;

    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
        $this->write(static::$configureOutputMessage);

        $sortedClasses = $this->getSortedClasses($package, static::$optionName);

        if (\count($sortedClasses) === 0) {
            return;
        }

        foreach ($sortedClasses as $env => $providers) {
            $filePath = $this->getConfFile($env);

            if ($this->isFileMarked($package->getName(), $filePath)) {
                continue;
            }

            $this->dump(
                $filePath,
                $this->generateFileContent($package, $filePath, $providers, $env)
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
        $this->write(static::$unconfigureOutputMessage);

        $sortedClasses = $this->getSortedClasses($package, static::$optionName);

        if (\count($sortedClasses) === 0) {
            return;
        }

        $envs = \array_keys($sortedClasses);

        $contents = [];

        foreach ($envs as $env) {
            $filePath = $this->getConfFile($env);

            if (! $this->isFileMarked($package->getName(), $filePath)) {
                continue;
            }

            $contents[$env] = \file_get_contents($filePath);

            \unlink($filePath);
        }

        foreach ($sortedClasses as $env => $classes) {
            foreach ($classes as $class) {
                if (! isset($contents[$env])) {
                    continue;
                }

                $contents[$env] = $this->replaceContent($class, $contents[$env]);
            }
        }

        $spaces = \str_repeat(' ', static::$spaceMultiplication);

        foreach ($contents as $key => $content) {
            $content = \str_replace([$spaces . '/** > ' . $package->getName() . " **/\n", $spaces . '/** ' . $package->getName() . " < **/\n"], '', $content);

            $this->dump($this->getConfFile($key), $content);
        }
    }

    /**
     * Dump file content.
     *
     * @param string $filePath
     * @param string $content
     *
     * @return void
     */
    protected function dump(string $filePath, string $content): void
    {
        $this->filesystem->dumpFile($filePath, $content);

        // @codeCoverageIgnoreStart
        if (\function_exists('opcache_invalidate')) {
            \opcache_invalidate($filePath);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get service providers config file.
     *
     * @param string $type
     *
     * @return string
     */
    protected function getConfFile(string $type): string
    {
        $type = $type === 'global' ? '' : $type;

        return self::expandTargetDir($this->options, '%CONFIG_DIR%/' . $type . '/' . static::$configFileName . '.php');
    }

    /**
     * Generate file content.
     *
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
     * @param string                                         $filePath
     * @param array                                          $classes
     * @param string                                         $env
     *
     * @return string
     */
    abstract protected function generateFileContent(PackageContract $package, string $filePath, array $classes, string $env): string;

    /**
     * Replace a string in content.
     *
     * @param string $class
     * @param string $content
     *
     * @return string
     */
    abstract protected function replaceContent($class, $content): string;
}
