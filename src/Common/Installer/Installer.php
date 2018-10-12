<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Installer;

use Composer\Composer;
use Composer\Config;
use Composer\Installer as BaseInstaller;
use Composer\Installer\SuggestedPackagesReporter;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
final class Installer
{
    /**
     * Private constructor; non-instantiable.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * Create a configured Composer Installer.
     *
     * @param \Composer\IO\IOInterface                        $io
     * @param \Composer\Composer                              $composer
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return \Composer\Installer
     */
    public static function create(IOInterface $io, Composer $composer, InputInterface $input): BaseInstaller
    {
        $config    = $composer->getConfig();
        $installer = new BaseInstaller(
            $io,
            $config,
            $composer->getPackage(),
            $composer->getDownloadManager(),
            $composer->getRepositoryManager(),
            $composer->getLocker(),
            $composer->getInstallationManager(),
            $composer->getEventDispatcher(),
            $composer->getAutoloadGenerator()
        );

        [$preferSource, $preferDist] = self::getPreferredInstallOptions($config, $input);

        $optimize      = self::getOption($input, 'optimize-autoloader')    || (bool) $config->get('optimize-autoloader');
        $authoritative = self::getOption($input, 'classmap-authoritative') || (bool) $config->get('classmap-authoritative');
        $apcu          = self::getOption($input, 'apcu-autoloader')        || (bool) $config->get('apcu-autoloader');

        $installer
            ->disablePlugins()
            ->setPreferSource($preferSource)
            ->setPreferDist($preferDist)
            ->setDryRun(self::getOption($input, 'dry-run'))
            ->setVerbose(self::getOption($input, 'verbose'))
            ->setDevMode(! self::getOption($input, 'no-dev'))
            ->setSuggestedPackagesReporter(new SuggestedPackagesReporter(new NullIO()))
            ->setDumpAutoloader(! self::getOption($input, 'no-autoloader'))
            ->setRunScripts(! self::getOption($input, 'no-scripts'))
            ->setOptimizeAutoloader($optimize)
            ->setClassMapAuthoritative($authoritative)
            ->setApcuAutoloader($apcu)
            ->setIgnorePlatformRequirements(self::getOption($input, 'ignore-platform-reqs'))
            ->setUpdate(true);

        return $installer;
    }

    /**
     * Returns preferSource and preferDist values based on the configuration.
     *
     * @param \Composer\Config                                $config
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return bool[] An array composed of the preferSource and preferDist values
     */
    private static function getPreferredInstallOptions(Config $config, InputInterface $input): array
    {
        $preferSource = false;
        $preferDist   = false;
        // @codeCoverageIgnoreStart
        switch ($config->get('preferred-install')) {
            case 'source':
                $preferSource = true;

                break;
            case 'dist':
                $preferDist = true;

                break;
            case 'auto':
            default:
                // noop
                break;
        }
        // @codeCoverageIgnoreEnd
        $preferSource = self::getOption($input, 'prefer-source', $preferSource);
        $preferDist   = self::getOption($input, 'prefer-dist', $preferDist);

        return [$preferSource, $preferDist];
    }

    /**
     * Returns default if options is not found.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param string                                          $name
     * @param bool                                            $default
     *
     * @return bool
     */
    private static function getOption(InputInterface $input, string $name, bool $default = false): bool
    {
        return $input->hasOption($name) ? (bool) $input->getOption($name) : $default;
    }
}
