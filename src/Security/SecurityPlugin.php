<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security;

use Composer\Composer;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\Event as EventDispatcherEvent;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Package\Locker;
use Composer\Plugin\Capability\CommandProvider as CommandProviderContract;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
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
    public const VERSION = '0.7.0';

    /**
     * @var string
     */
    public const COMPOSER_EXTRA_KEY = 'audit';

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
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        if (! self::$activated) {
            return [];
        }

        return [
            PluginEvents::INIT                  => 'onInit',
            'post-install-out'                  => [['postInstallOut', \PHP_INT_MAX]],
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
        if (($errorMessage = $this->getErrorMessage()) !== null) {
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
     * {@inheritdoc}
     */
    public function getCapabilities(): array
    {
        return [
            CommandProviderContract::class => CommandProvider::class,
        ];
    }

    /**
     * Occurs after a Composer instance is done being initialized.
     *
     * @param \Composer\EventDispatcher\Event $event
     *
     * @return void
     */
    public function onInit(EventDispatcherEvent $event): void
    {
        $scripts = $this->composer->getPackage()->getScripts();

        if (isset($scripts['post-install-out'])) {
            return;
        }

        $composerFilePath = Factory::getComposerFile();
        $manipulator      = new JsonManipulator(\file_get_contents($composerFilePath));

        if (\count($scripts) === 0) {
            $manipulator->addMainKey('scripts', []);
        }

        $manipulator->addSubNode('scripts', 'post-install-out', 'This key is needed for Narrowspark to show messages.');

        $scriptKey = '@post-install-out';

        if (! \in_array($scriptKey, $scripts['post-install-cmd'] ?? [], true)) {
            $manipulator->addSubNode('scripts', 'post-install-cmd', \array_merge($scripts['post-install-cmd'] ?? [], [$scriptKey]));
        }

        if (! \in_array($scriptKey, $scripts['post-update-cmd'] ?? [], true)) {
            $manipulator->addSubNode('scripts', 'post-update-cmd', \array_merge($scripts['post-update-cmd'] ?? [], [$scriptKey]));
        }

        \file_put_contents($composerFilePath, $manipulator->getContents());

        $this->updateComposerLock();
    }

    /**
     * Execute on composer post-install-out event.
     *
     * @param \Composer\Script\Event $event
     *
     * @return void
     */
    public function postInstallOut(Event $event): void
    {
        $event->stopPropagation();

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

    /**
     * Update composer.lock file with the composer.json change.
     *
     * @throws \Exception
     *
     * @return void
     */
    private function updateComposerLock(): void
    {
        $composerLockPath = Util::getComposerLockFile();
        $composerJson     = \file_get_contents(Factory::getComposerFile());

        $lockFile = new JsonFile($composerLockPath, null, $this->io);
        $locker   = new Locker(
            $this->io,
            $lockFile,
            $this->composer->getRepositoryManager(),
            $this->composer->getInstallationManager(),
            (string) $composerJson
        );

        $lockData                  = $locker->getLockData();
        $lockData['content-hash']  = Locker::getContentHash((string) $composerJson);

        $lockFile->write($lockData);
    }
}
