<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\Automatic\Security;

use Composer\Composer;
use Composer\Config;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderContract;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents as ComposerScriptEvents;
use FilesystemIterator;
use Narrowspark\Automatic\Common\AbstractContainer;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Security\Common\Util;
use Narrowspark\Automatic\Security\Contract\Downloader as DownloaderContract;
use Narrowspark\Automatic\Security\Contract\Exception\RuntimeException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use const DIRECTORY_SEPARATOR;
use const PHP_INT_MAX;
use function array_filter;
use function array_key_exists;
use function class_exists;
use function count;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;
use function version_compare;

class Plugin implements Capable, EventSubscriberInterface, PluginInterface
{
    /** @var string */
    public const VERSION = '0.12.0';

    /** @var string */
    public const COMPOSER_EXTRA_KEY = 'audit';

    /** @var string */
    public const PACKAGE_NAME = 'narrowspark/automatic-security-audit';

    /**
     * Found package vulnerabilities.
     *
     * @var array[]
     */
    private $foundVulnerabilities = [];

    /**
     * Check if the the plugin is activated.
     *
     * @var bool
     */
    private static $activated = true;

    /**
     * Sha of the security security-advisories.json.
     *
     * @var string
     */
    private $securitySha;

    /**
     * A Container instance.
     *
     * @var \Narrowspark\Automatic\Common\Contract\Container
     */
    protected $container;

    /**
     * Check if the package should run in uninstall mode.
     *
     * @var bool
     */
    private $uninstallMode = false;

    /**
     * Get the Container instance.
     *
     * @return \Narrowspark\Automatic\Common\Contract\Container
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
            PackageEvents::POST_PACKAGE_INSTALL => [['auditPackage', ~PHP_INT_MAX]],
            PackageEvents::POST_PACKAGE_UPDATE => [['auditPackage', ~PHP_INT_MAX]],
            ComposerScriptEvents::POST_INSTALL_CMD => [['auditComposerLock', PHP_INT_MAX]],
            ComposerScriptEvents::POST_UPDATE_CMD => [['auditComposerLock', PHP_INT_MAX], ['onPostUpdatePostMessages', ~PHP_INT_MAX]],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        // to avoid issues when Automatic is upgraded, we load all PHP classes now
        // that way, we are sure to use all files from the same version.
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)) as $file) {
            /** @var SplFileInfo $file */
            if (substr($file->getFilename(), -4) === '.php') {
                class_exists(__NAMESPACE__ . str_replace('/', '\\', substr($file->getFilename(), strlen(__DIR__), -4)));
            }
        }

        if (! class_exists(AbstractContainer::class)) {
            require __DIR__ . DIRECTORY_SEPARATOR . 'alias.php';
        }

        $this->container = new Container($composer, $io);

        $extra = $this->container->get('composer-extra');
        $downloader = $this->container->get(DownloaderContract::class);

        if (array_key_exists(self::COMPOSER_EXTRA_KEY, $extra) && array_key_exists('timeout', $extra[self::COMPOSER_EXTRA_KEY])) {
            $downloader->setTimeout($extra[self::COMPOSER_EXTRA_KEY]['timeout']);
        }

        if (($errorMessage = $this->getErrorMessage($io, $downloader)) !== null) {
            self::$activated = false;

            $io->writeError('<warning>Narrowspark Automatic Security Audit has been disabled. ' . $errorMessage . '</warning>');

            $extra = null;

            return;
        }

        $this->container->set(Audit::class, function (ContainerContract $container) {
            $audit = new Audit(rtrim($container->get(Config::class)->get('vendor-dir'), '/'), $container->get(DownloaderContract::class), $this->securitySha);

            $name = 'no-dev';
            $input = $container->get(InputInterface::class);

            $audit->setDevMode($input->hasOption($name) ? ! (bool) $input->getOption($name) : true);

            return $audit;
        });

        // Downloading needed security advisories database.
        $this->container->get('security_advisories');
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
     * Execute on composer post-messages event.
     *
     * @param \Composer\Script\Event $event
     *
     * @return void
     */
    public function onPostUpdatePostMessages(Event $event): void
    {
        if ($this->uninstallMode) {
            return;
        }

        $count = count(array_filter($this->foundVulnerabilities));
        $io = $this->container->get(IOInterface::class);

        if ($count !== 0) {
            $io->write('<error>[!]</> Audit Security Report: ' . sprintf('%s vulnerabilit%s found - run "composer audit" for more information', $count, $count === 1 ? 'y' : 'ies'));
        } else {
            $io->write('<fg=black;bg=green>[+]</> Audit Security Report: No known vulnerabilities found');
        }
    }

    /**
     * Audit composer package operations.
     *
     * @param \Composer\Installer\PackageEvent $event
     *
     * @return void
     */
    public function auditPackage(PackageEvent $event): void
    {
        $operation = $event->getOperation();

        if ($operation instanceof UninstallOperation) {
            if ($operation->getPackage()->getPrettyName() === self::PACKAGE_NAME) {
                $this->uninstallMode = true;
            }

            return;
        }

        if ($operation instanceof UpdateOperation) {
            $composerPackage = $operation->getTargetPackage();
        } else {
            $composerPackage = $operation->getPackage();
        }

        $data = $this->container->get(Audit::class)->checkPackage(
            $composerPackage->getName(),
            $composerPackage->getVersion(),
            $this->container->get('security_advisories')
        );

        if (count($data) === 0) {
            return;
        }

        $this->foundVulnerabilities += $data[0];
    }

    /**
     * Audit composer.lock.
     *
     * @param \Composer\Script\Event $event
     *
     * @return void
     */
    public function auditComposerLock(Event $event): void
    {
        if ($this->uninstallMode || count($this->foundVulnerabilities) !== 0) {
            return;
        }

        $data = $this->container->get(Audit::class)->checkLock(Util::getComposerLockFile());

        if (count($data) === 0) {
            return;
        }

        $this->foundVulnerabilities += $data[0];
    }

    /**
     * Check if automatic can be activated.
     *
     * @param \Composer\IO\IOInterface                            $io
     * @param \Narrowspark\Automatic\Security\Contract\Downloader $downloader
     *
     * @return null|string
     */
    private function getErrorMessage(IOInterface $io, DownloaderContract $downloader): ?string
    {
        // @codeCoverageIgnoreStart
        if (version_compare(self::getComposerVersion(), '1.7.0', '<')) {
            return sprintf('Your version "%s" of Composer is too old; Please upgrade', Composer::VERSION);
        }
        // @codeCoverageIgnoreEnd

        try {
            $io->writeError('Narrowspark Automatic Security Audit is checking for internet connection...', true, IOInterface::VERBOSE);

            $this->securitySha = $downloader->download(Audit::SECURITY_ADVISORIES_BASE_URL . Audit::SECURITY_ADVISORIES_SHA);
        } catch (RuntimeException $exception) {
            return 'Connecting to github.com failed.';
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
        preg_match('/\d+.\d+.\d+/m', Composer::VERSION, $matches);

        if ($matches !== null) {
            return $matches[0];
        }

        preg_match('/\d+.\d+.\d+/m', Composer::BRANCH_ALIAS_VERSION, $matches);

        if ($matches !== null) {
            return $matches[0];
        }

        throw new RuntimeException('No composer version found.');
    }
}
