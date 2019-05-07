<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security;

use Composer\Composer;
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
use Narrowspark\Automatic\Security\Contract\Exception\RuntimeException;
use Narrowspark\Automatic\Security\Downloader\ComposerDownloader;
use Narrowspark\Automatic\Security\Downloader\CurlDownloader;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SecurityPlugin implements PluginInterface, EventSubscriberInterface, Capable
{
    /**
     * @var string
     */
    public const VERSION = '0.11.0';

    /**
     * @var string
     */
    public const COMPOSER_EXTRA_KEY = 'audit';

    /**
     * @var string
     */
    public const PACKAGE_NAME = 'narrowspark/automatic-security-audit';

    /**
     * The SecurityAdvisories database.
     *
     * @var array<string, array>
     */
    protected $securityAdvisories = [];

    /**
     * Found package vulnerabilities.
     *
     * @var array[]
     */
    protected $foundVulnerabilities = [];

    /**
     * The composer instance.
     *
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * A Audit instance.
     *
     * @var \Narrowspark\Automatic\Security\Audit
     */
    private $audit;

    /**
     * Check if the the plugin is activated.
     *
     * @var bool
     */
    private static $activated = true;

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        if (! self::$activated) {
            return [];
        }

        return [
            PackageEvents::POST_PACKAGE_INSTALL     => [['auditPackage', ~\PHP_INT_MAX]],
            PackageEvents::POST_PACKAGE_UPDATE      => [['auditPackage', ~\PHP_INT_MAX]],
            ComposerScriptEvents::POST_INSTALL_CMD  => [['auditComposerLock', \PHP_INT_MAX]],
            ComposerScriptEvents::POST_UPDATE_CMD   => [['auditComposerLock', \PHP_INT_MAX], ['onPostUpdatePostMessages', ~\PHP_INT_MAX]],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        if (($errorMessage = $this->getErrorMessage()) !== null) {
            self::$activated = false;

            $io->writeError('<warning>Narrowspark Automatic Security Audit has been disabled. ' . $errorMessage . '</warning>');

            return;
        }

        // to avoid issues when Automatic is upgraded, we load all PHP classes now
        // that way, we are sure to use all files from the same version.
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)) as $file) {
            /** @var \SplFileInfo $file */
            if (\substr($file->getFilename(), -4) === '.php') {
                require_once $file;
            }
        }

        $this->composer = $composer;
        $this->io       = $io;

        if (\extension_loaded('curl')) {
            $downloader = new CurlDownloader();
        } else {
            $downloader = new ComposerDownloader();
        }

        $extra = $composer->getPackage()->getExtra();

        if (isset($extra[self::COMPOSER_EXTRA_KEY]['timeout'])) {
            $downloader->setTimeout($extra[self::COMPOSER_EXTRA_KEY]['timeout']);
        }

        $this->audit = new Audit(\rtrim($composer->getConfig()->get('vendor-dir'), '/'), $downloader);

        $this->securityAdvisories = $this->audit->getSecurityAdvisories($io);
    }

    /**
     * {@inheritDoc}
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
        $count = \count(\array_filter($this->foundVulnerabilities));

        if ($count !== 0) {
            $this->io->write('<error>[!]</> Audit Security Report: ' . \sprintf('%s vulnerabilit%s found - run "composer audit" for more information', $count, $count === 1 ? 'y' : 'ies'));
        } else {
            $this->io->write('<fg=black;bg=green>[+]</> Audit Security Report: No known vulnerabilities found');
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
            return;
        }

        if ($operation instanceof UpdateOperation) {
            $composerPackage = $operation->getTargetPackage();
        } else {
            $composerPackage = $operation->getPackage();
        }

        $data = $this->audit->checkPackage(
            $composerPackage->getName(),
            $composerPackage->getVersion(),
            $this->securityAdvisories
        );

        if (\count($data) === 0) {
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
        if (\count($this->foundVulnerabilities) !== 0) {
            return;
        }

        $data = $this->audit->checkLock(Util::getComposerLockFile());

        if (\count($data) === 0) {
            return;
        }

        $this->foundVulnerabilities += $data[0];
    }

    /**
     * Check if automatic can be activated.
     *
     * @return null|string
     */
    private function getErrorMessage(): ?string
    {
        // @codeCoverageIgnoreStart
        if (\version_compare(self::getComposerVersion(), '1.6.0', '<')) {
            return \sprintf('Your version "%s" of Composer is too old; Please upgrade', Composer::VERSION);
        }
        // @codeCoverageIgnoreEnd

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
