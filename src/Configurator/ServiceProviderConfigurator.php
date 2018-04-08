<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Configurator;

use Narrowspark\Discovery\Package;
use Viserio\Component\Foundation\AbstractKernel;

final class ServiceProviderConfigurator extends AbstractConfigurator
{
    /**
     * Return only the commentary for the global array.
     *
     * @return string
     */
    public static function getGlobalServiceProviderCommentary(): string
    {
        return '

/*
|--------------------------------------------------------------------------
| Autoloaded Service Providers
|--------------------------------------------------------------------------
|
| The service providers listed here will be automatically loaded on the
| request to your application. Feel free to add your own services to
| this array to grant expanded functionality to your applications.
|
*/';
    }

    /**
     * Return only the commentary for the global array.
     *
     * @return string
     */
    public static function getLocalServiceProviderCommentary(): string
    {
        return '/*
 |--------------------------------------------------------------------------
 | Testing And Local Autoloaded Service Providers
 |--------------------------------------------------------------------------
 |
 | Some providers are only used while developing the application or during
 | the unit and functional tests. Therefore, they are only registered
 | when the application runs in \'local\' or \'testing\' environments. This allows
 | to increase application performance in the production environment.
 |
 */';
    }

    /**
     * {@inheritdoc}
     */
    public function configure(Package $package): void
    {
        $this->write('Enabling the package as a Narrowspark service-provider');

        $filePath = $this->getConfFile();

        if ($this->isFileMarked($package->getName(), $filePath)) {
            return;
        }

        [$global, $local] = $this->getPackageGlobalAndLocalProviders($package);

        if (\count($global) === 0 && \count($local) === 0) {
            return;
        }

        $content = $this->generateServiceProviderFileContent($package, $filePath, $global, $local);

        $this->dump($filePath, $content);
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(Package $package): void
    {
        $this->write('Disable the Narrowspark service-provider');

        $filePath = $this->getConfFile();

        if (! $this->isFileMarked($package->getName(), $filePath)) {
            return;
        }

        [$global, $local] = $this->getPackageGlobalAndLocalProviders($package);

        if (\count($global) === 0 && \count($local) === 0) {
            return;
        }

        $content = \file_get_contents($filePath);

        \unlink($filePath);

        foreach ($global as $provider) {
            $content = \str_replace('    ' . $provider . ',', '', $content);
        }

        foreach ($local as $provider) {
            $content = \str_replace('    $providers[] = ' . $provider . ';', '', $content);
        }

        $content = \str_replace(["    /** > %s **/\n", "    /** %s < **/\n"], '', $content);

        $this->dump($filePath, $content);
    }

    /**
     * {@inheritdoc}
     */
    protected function isFileMarked(string $packageName, string $file): bool
    {
        return \is_file($file) && \mb_strpos(\file_get_contents($file), \sprintf('/** > %s **/', $packageName)) !== false;
    }

    /**
     * {@inheritdoc}
     */
    protected function markData(string $packageName, string $data): string
    {
        return \sprintf("    /** > %s **/\n%s    /** %s < **/\n", $packageName, $data, $packageName);
    }

    /**
     * Generates the global and local service provider file content.
     *
     * @param \Narrowspark\Discovery\Package $package
     * @param string                         $filePath
     * @param array                          $global
     * @param array                          $local
     *
     * @return string
     */
    private function generateServiceProviderFileContent(Package $package, string $filePath, array $global, array $local): string
    {
        if (\file_exists($filePath)) {
            $content = \file_get_contents($filePath);

            \unlink($filePath);
        } else {
            $content = '<?php
declare(strict_types=1);';
        }

        if (\count($global) !== 0) {
            $endOfArrayPosition = \mb_strpos($content, '];');

            if ($endOfArrayPosition === false) {
                $content .= $this->createGlobalServiceProviderContentWithCommentary($package, $global);
            } else {
                $globalContent = $this->buildGlobalServiceProviderContent($package, $global);
                $content       = $this->doInsertStringBeforePosition($content, $globalContent, $endOfArrayPosition);
            }
        } elseif (\mb_strpos($content, '$providers = [') === false) {
            $content .= self::getGlobalServiceProviderCommentary();
            $content .= "\n\$providers = [\n\n];\n\nreturn \$providers;\n";
        }

        if (\class_exists(AbstractKernel::class) === true && \count($local) !== 0) {
            $endOfIfPosition     = \mb_strpos($content, "}\n");
            $endOfReturnPosition = \mb_strpos($content, 'return $providers;');

            if ($endOfIfPosition !== false) {
                $localContent = $this->buildLocalServiceProviderContent($package, $local);
                $content      = $this->doInsertStringBeforePosition($content, $localContent, $endOfIfPosition);
            } elseif ($endOfReturnPosition !== false) {
                $localContent = $this->createLocalServiceProviderContentWithCommentary($package, $local);
                $content      = $this->doInsertStringBeforePosition($content, $localContent . "\n\n", $endOfReturnPosition);
            }
        }

        return $content;
    }

    /**
     * Insert string at specified position.
     *
     * @param string $string
     * @param string $insertStr
     * @param int    $position
     *
     * @return string
     */
    private function doInsertStringBeforePosition(string $string, string $insertStr, int $position): string
    {
        return \mb_substr($string, 0, $position) . $insertStr . \mb_substr($string, $position);
    }

    /**
     * Builds a global service provider array value.
     *
     * @param \Narrowspark\Discovery\Package $package
     * @param array                          $global
     *
     * @return string
     */
    private function buildGlobalServiceProviderContent(Package $package, array $global): string
    {
        $globalContent = '';

        foreach ($global as $provider) {
            $globalContent .= '    ' . $provider . ",\n";

            $this->write(\sprintf('Enabling "%s" as a global service provider.', $provider));
        }

        return $this->markData($package->getName(), $globalContent);
    }

    /**
     * Builds a local service provider array value.
     *
     * @param \Narrowspark\Discovery\Package $package
     * @param array                          $local
     *
     * @return string
     */
    private function buildLocalServiceProviderContent(Package $package, array $local): string
    {
        $localContent = '';

        foreach ($local as $provider) {
            $localContent .= '    $providers[] = ' . $provider . ";\n";

            $this->write(\sprintf('Enabling "%s" as a local service provider.', $provider));
        }

        return $this->markData($package->getName(), $localContent);
    }

    /**
     * Creates the basic global array with commentary.
     *
     * @param \Narrowspark\Discovery\Package $package
     * @param array                          $global
     *
     * @return string
     */
    private function createGlobalServiceProviderContentWithCommentary(Package $package, array $global): string
    {
        $content  = self::getGlobalServiceProviderCommentary();
        $content .= "\n\$providers = [\n";
        $content .= $this->buildGlobalServiceProviderContent($package, $global);
        $content .= "];\n\nreturn \$providers;\n";

        return $content;
    }

    /**
     * Creates the local basic array with commentary.
     *
     * @param \Narrowspark\Discovery\Package $package
     * @param array                          $local
     *
     * @return string
     */
    private function createLocalServiceProviderContentWithCommentary(Package $package, array $local): string
    {
        $content = self::getLocalServiceProviderCommentary();
        $content .= "\nif (\$kernel->isLocal() || \$kernel->isRunningUnitTests()) {\n";
        $content .= $this->buildLocalServiceProviderContent($package, $local);
        $content .= '}';

        return $content;
    }

    /**
     * Get service providers config file.
     *
     * @return string
     */
    private function getConfFile(): string
    {
        return $this->expandTargetDir($this->options, '%CONFIG_DIR%/serviceproviders.php');
    }

    /**
     * Dump file content.
     *
     * @param string $filePath
     * @param string $content
     *
     * @return void
     */
    private function dump(string $filePath, string $content): void
    {
        $this->filesystem->dumpFile($filePath, $content);

        // @codeCoverageIgnoreStart
        if (\function_exists('opcache_invalidate')) {
            \opcache_invalidate($filePath);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get arrays of global and local providers.
     *
     * @param \Narrowspark\Discovery\Package $package
     *
     * @return array
     */
    private function getPackageGlobalAndLocalProviders(Package $package): array
    {
        $global = [];
        $local  = [];

        foreach ($package->getConfiguratorOptions('providers') as $name => $providers) {
            if ($name === 'global') {
                foreach ($providers as $provider) {
                    $class = \mb_strpos($provider, '::class') !== false ? $provider : $provider . '::class';

                    $global[$class] = $class;
                }
            } elseif ($name === 'local') {
                foreach ($providers as $provider) {
                    $class = \mb_strpos($provider, '::class') !== false ? $provider : $provider . '::class';

                    $local[$class] = $class;
                }
            }
        }

        return [$global, $local];
    }
}
