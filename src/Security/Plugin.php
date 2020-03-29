<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018-2020 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/automatic
 */

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
use Narrowspark\Automatic\Common\AbstractContainer;
use Narrowspark\Automatic\Common\Util;
use Narrowspark\Automatic\Security\Contract\Audit as AuditContract;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;

final class Plugin implements Capable, EventSubscriberInterface, PluginInterface
{
    /** @var string */
    public const VERSION = '0.13.1';

    /** @var string */
    public const COMPOSER_EXTRA_KEY = 'audit';

    /** @var string */
    public const PACKAGE_NAME = 'narrowspark/automatic-security-audit';

    /** @var string */
    private const NAME = 'no-dev';

    /**
     * A Container instance.
     *
     * @var \Narrowspark\Automatic\Common\Contract\Container
     */
    private $container;

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
     * Check if the package should run in uninstall mode.
     *
     * @var bool
     */
    private $uninstallMode = false;

    /**
     * {@inheritdoc}
     *
     * @return mixed[][][]
     */
    public static function getSubscribedEvents(): array
    {
        if (! self::$activated) {
            return [];
        }

        return [
            PackageEvents::POST_PACKAGE_INSTALL => [['auditPackage', ~\PHP_INT_MAX]],
            PackageEvents::POST_PACKAGE_UPDATE => [['auditPackage', ~\PHP_INT_MAX]],
            ComposerScriptEvents::POST_INSTALL_CMD => [['auditComposerLock', \PHP_INT_MAX]],
            ComposerScriptEvents::POST_UPDATE_CMD => [['auditComposerLock', \PHP_INT_MAX], ['onPostUpdatePostMessages', ~\PHP_INT_MAX]],
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
            if (\substr($file->getFilename(), -4) === '.php') {
                \class_exists(__NAMESPACE__ . \str_replace('/', '\\', \substr($file->getFilename(), \strlen(__DIR__), -4)));
            }
        }

        if (! \class_exists(AbstractContainer::class)) {
            require __DIR__ . \DIRECTORY_SEPARATOR . 'alias.php';
        }

        if (($errorMessage = $this->getErrorMessage()) !== null) {
            self::$activated = false;

            $io->writeError('<warning>Narrowspark Automatic Security Audit has been disabled. ' . $errorMessage . '</warning>');

            return;
        }

        $this->container = new Container($composer, $io);

        /** @var null|\Symfony\Component\Console\Input\InputInterface $input */
        $input = $this->container->get(InputInterface::class);

        if ($input === null) {
            self::$activated = false;

            $io->writeError('<warning>Narrowspark Automatic Security Audit has been disabled. No input object found on composer class.</warning>');

            return;
        }

        /** @var AuditContract $audit */
        $audit = $this->container->get(AuditContract::class);
        $devMode = true;

        if ($input->hasOption(self::NAME)) {
            $devMode = ! (bool) $input->getOption(self::NAME);
        }

        $audit->setDevMode($devMode);
    }

    /**
     * {@inheritdoc}
     *
     * @return string[]
     */
    public function getCapabilities(): array
    {
        return [
            CommandProviderContract::class => CommandProvider::class,
        ];
    }

    /**
     * Execute on composer post-messages event.
     */
    public function onPostUpdatePostMessages(Event $event): void
    {
        if ($this->uninstallMode) {
            return;
        }

        $count = \count(\array_filter($this->foundVulnerabilities));
        $io = $this->container->get(IOInterface::class);

        if ($count !== 0) {
            $io->write('<error>[!]</> Audit Security Report: ' . \sprintf('%s vulnerabilit%s found - run "composer audit" for more information', $count, $count === 1 ? 'y' : 'ies'));
        } else {
            $io->write('<fg=black;bg=green>[+]</> Audit Security Report: No known vulnerabilities found');
        }
    }

    /**
     * Audit composer package operations.
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

        $data = $this->container->get(AuditContract::class)->checkPackage(
            $composerPackage->getName(),
            $composerPackage->getVersion(),
            $this->container->get('security_advisories')
        );

        if ((\is_countable($data) ? \count($data) : 0) === 0) {
            return;
        }

        $this->foundVulnerabilities += $data[0];
    }

    /**
     * Audit composer.lock.
     */
    public function auditComposerLock(): void
    {
        if ($this->uninstallMode || \count($this->foundVulnerabilities) !== 0) {
            return;
        }

        /** @var \Narrowspark\Automatic\Security\Contract\Audit $audit */
        $audit = $this->container->get(AuditContract::class);
        $data = $audit->checkLock(Util::getComposerLockFile());

        if (\count($data) === 0) {
            return;
        }

        $this->foundVulnerabilities += $data[0];
    }

    /**
     * Check if automatic can be activated.
     */
    private function getErrorMessage(): ?string
    {
        // @codeCoverageIgnoreStart
        if (\version_compare(Util::getComposerVersion(), '1.8.0', '<')) {
            return \sprintf('Your version "%s" of Composer is too old; Please upgrade', Composer::VERSION);
        }
        // @codeCoverageIgnoreEnd

        return null;
    }
}
