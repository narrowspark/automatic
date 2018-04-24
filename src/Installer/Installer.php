<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Installer;

use Composer\Composer;
use Composer\Config;
use Composer\Installer as BaseInstaller;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Input\InputInterface;

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
        $installer = BaseInstaller::create($io, $composer);
        $config    = $composer->getConfig();

        [$preferSource, $preferDist] = self::getPreferredInstallOptions($config, $input);

        $optimize      = self::getOption($input, 'optimize-autoloader', false)    || $config->get('optimize-autoloader');
        $authoritative = self::getOption($input, 'classmap-authoritative', false) || $config->get('classmap-authoritative');

        $installer
            ->disablePlugins()
            ->setDryRun(self::getOption($input, 'dry-run', false))
            ->setVerbose(self::getOption($input, 'verbose', false))
            ->setPreferSource($preferSource)
            ->setPreferDist($preferDist)
            ->setDevMode(! self::getOption($input, 'no-dev', false))
            ->setRunScripts(false)
            ->setSkipSuggest(self::getOption($input, 'no-suggest', false))
            ->setOptimizeAutoloader($optimize)
            ->setClassMapAuthoritative($authoritative)
            ->setUpdate(true);

        return $installer;
    }

    /**
     *  Returns default if options is not found.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param string                                          $name
     * @param bool                                            $default
     *
     * @return bool
     */
    private static function getOption(InputInterface $input, string $name, bool $default): bool
    {
        return $input->hasOption($name) ? (bool) $input->getOption($name) : $default;
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

        $preferSource = self::getOption($input, 'prefer-source', $preferSource);
        $preferDist   = self::getOption($input, 'prefer-dist', $preferDist);

        return [$preferSource, $preferDist];
    }
}
