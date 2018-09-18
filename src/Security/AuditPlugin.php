<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderContract;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use FilesystemIterator;
use Narrowspark\Automatic\Security\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Security\Contract\Exception\RuntimeException;
use Narrowspark\Automatic\Security\Downloader\ComposerDownloader;
use Narrowspark\Automatic\Security\Downloader\CurlDownloader;
use Narrowspark\Automatic\Security\Traits\SecurityPluginTrait;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AuditPlugin implements PluginInterface, EventSubscriberInterface, Capable
{
    use SecurityPluginTrait;

    /**
     * @var string
     */
    public const VERSION = '0.7.0';

    /**
     * @var string
     */
    public const COMPOSER_EXTRA_KEY = 'automatic';

    /**
     * A Container instance.
     *
     * @var \Narrowspark\Automatic\Security\Contract\Container
     */
    protected $container;

    /**
     * Check if the the plugin is activated.
     *
     * @var bool
     */
    private static $activated = true;

    /**
     * Get the Container instance.
     *
     * @return \Narrowspark\Automatic\Security\Contract\Container
     */
    public function getContainer(): ContainerContract
    {
        return $this->container;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        if (! self::$activated) {
            return [];
        }

        return [
            PackageEvents::POST_PACKAGE_INSTALL => [['auditPackage', \PHP_INT_MAX]],
            PackageEvents::POST_PACKAGE_UPDATE  => [['auditPackage', \PHP_INT_MAX]],
            ScriptEvents::POST_INSTALL_CMD      => [['auditComposerLock', \PHP_INT_MAX]],
            ScriptEvents::POST_UPDATE_CMD       => [['auditComposerLock', \PHP_INT_MAX]],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        if (($errorMessage = $this->getErrorMessage($io)) !== null) {
            self::$activated = false;

            $io->writeError('<warning>Narrowspark Automatic has been disabled. ' . $errorMessage . '</warning>');

            return;
        }

        // to avoid issues when Automatic is upgraded, we load all PHP classes now
        // that way, we are sure to use all files from the same version.
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)) as $file) {
            /** @var \SplFileInfo $file */
            if (\mb_substr($file->getFilename(), -4) === '.php') {
                require_once $file;
            }
        }

        $this->container = new Container($composer, $io);

        $extra = $this->container->get('composer-extra');

        if (\extension_loaded('curl')) {
            $downloader = new CurlDownloader();
        } else {
            $downloader = new ComposerDownloader();
        }

        if (isset($extra[self::COMPOSER_EXTRA_KEY]['audit']['timeout'])) {
            $downloader->setTimeout($extra[self::COMPOSER_EXTRA_KEY]['audit']['timeout']);
        }

        $this->container->set(Audit::class, static function (Container $container) use ($downloader) {
            return new Audit($container->get('vendor-dir'), $downloader);
        });

        $this->securityAdvisories = $this->container->get(Audit::class)->getSecurityAdvisories($io);
    }

    /**
     * {@inheritdoc}
     */
    public function getCapabilities(): array
    {
        return [
            CommandProviderContract::class => CommandProvider::class,
        ];
    }

    /**
     * Check if automatic can be activated.
     *
     * @param \Composer\IO\IOInterface $io
     *
     * @return null|string
     */
    private function getErrorMessage(IOInterface $io): ?string
    {
        // @codeCoverageIgnoreStart
        if (! \extension_loaded('openssl')) {
            return 'You must enable the openssl extension in your "php.ini" file';
        }

        if (\version_compare(self::getComposerVersion(), '1.6.0', '<')) {
            return \sprintf('Your version "%s" of Composer is too old; Please upgrade', Composer::VERSION);
        }

        // @codeCoverageIgnoreEnd

        // skip on no interactive mode
        if (! $io->isInteractive()) {
            return 'Composer running in a no interaction mode';
        }

        return null;
    }

    /**
     * Get the composer version.
     *
     * @throws \Narrowspark\Automatic\Security\Contract\Exception\RuntimeException
     *
     * @return string
     */
    private static function getComposerVersion(): string
    {
        \preg_match('/\d+.\d+.\d+/m', Composer::VERSION, $matches);

        if ($matches !== null) {
            return $matches[0];
        }

        \preg_match('/\d+.\d+.\d+/m', Composer::BRANCH_ALIAS_VERSION, $matches);

        if ($matches !== null) {
            return $matches[0];
        }

        throw new RuntimeException('No composer version found.');
    }
}
