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
use Narrowspark\Automatic\Security\Contract\Container as ContainerContract;
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
    public const COMPOSER_EXTRA_KEY = 'automatic';

    /**
     * The SecurityAdvisories database.
     *
     * @var array<string, array>
     */
    protected $securityAdvisories;

    /**
     * Found package vulnerabilities.
     *
     * @var array[]
     */
    protected $foundVulnerabilities = [];

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
     * Occurs after a Composer instance is done being initialized.
     *
     * @param \Composer\EventDispatcher\Event $event
     *
     * @return void
     */
    public function onInit(EventDispatcherEvent $event): void
    {
        $json             = new JsonFile(Factory::getComposerFile());
        $composerJsonData = $json->read();

        if (isset($composerJsonData['scripts']['post-install-out'])) {
            return;
        }

        $manipulator = new JsonManipulator(\file_get_contents($json->getPath()));

        $manipulator->addSubNode('scripts', 'post-install-out', 'This key is needed for Narrowspark Automatic to show package messages.');

        $scripts = ['@post-install-out'];

        $manipulator->addSubNode('scripts', 'post-install-cmd', $scripts);
        $manipulator->addSubNode('scripts', 'post-update-cmd', $scripts);

        \file_put_contents($json->getPath(), $manipulator->getContents());

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

        /** @var \Composer\IO\IOInterface $io */
        $io = $this->container->get(IOInterface::class);

        $count = \count(\array_filter($this->foundVulnerabilities));

        if ($count !== 0) {
            $io->write('<error>[!]</> Audit Security Report: ' . \sprintf('%s vulnerabilit%s found - run "composer audit" for more information', $count, $count === 1 ? 'y' : 'ies'));
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

        $data = $this->container->get(Audit::class)->checkLock(Util::getComposerLockFile());

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
        $composer         = $this->container->get(Composer::class);

        $lockFile = new JsonFile($composerLockPath, null, $this->container->get(IOInterface::class));
        $locker   = new Locker(
            $this->container->get(IOInterface::class),
            $lockFile,
            $composer->getRepositoryManager(),
            $composer->getInstallationManager(),
            (string) $composerJson
        );

        $lockData                  = $locker->getLockData();
        $lockData['content-hash']  = Locker::getContentHash((string) $composerJson);

        $lockFile->write($lockData);
    }
}
