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

namespace Narrowspark\Automatic;

use Composer\Command\GlobalCommand;
use Composer\Composer;
use Composer\Console\Application;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Installer\SuggestedPackagesReporter;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Json\JsonFile;
use Composer\Package\Locker;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents as ComposerScriptEvents;
use Exception;
use FilesystemIterator;
use InvalidArgumentException;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Traits\ExpandTargetDirTrait;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Common\Util;
use Narrowspark\Automatic\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Installer\ConfiguratorInstaller;
use Narrowspark\Automatic\Installer\InstallationManager;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\Operation\Install;
use Narrowspark\Automatic\Operation\Uninstall;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;
use stdClass;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;

class Automatic implements EventSubscriberInterface, PluginInterface
{
    use ExpandTargetDirTrait;
    use GetGenericPropertyReaderTrait;

    /** @var string */
    public const VERSION = '0.13.1';

    /** @var string */
    public const LOCK_CLASSMAP = 'classmap';

    /** @var string */
    public const LOCK_PACKAGES = 'packages';

    /** @var string */
    public const COMPOSER_EXTRA_KEY = 'automatic';

    /** @var string */
    public const PACKAGE_NAME = 'narrowspark/automatic';

    /**
     * A Container instance.
     *
     * @var \Narrowspark\Automatic\Common\Contract\Container
     */
    protected $container;

    /**
     * Check if the the plugin is activated.
     *
     * @var bool
     */
    private static $activated = true;

    /**
     * Check if the Configurators a loaded.
     *
     * @var bool
     */
    private static $configuratorsLoaded = false;

    /**
     * Check if composer.lock should be updated.
     *
     * @var bool
     */
    private $shouldUpdateComposerLock = false;

    /**
     * The composer install/update operations.
     *
     * @var array
     */
    private $operations = [];

    /**
     * The composer uninstall operations.
     *
     * @var array
     */
    private $uninstallOperations = [];

    /**
     * List of package messages.
     *
     * @var string[]
     */
    private $postMessages = [''];

    /**
     * Check for global command.
     *
     * @var bool
     */
    private static $isGlobalCommand = false;

    /**
     * Get the automatic.lock file path.
     */
    public static function getAutomaticLockFile(): string
    {
        return \str_replace('composer', self::COMPOSER_EXTRA_KEY, Util::getComposerLockFile());
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
            ScriptEvents::AUTO_SCRIPTS => 'executeAutoScripts',
            PackageEvents::PRE_PACKAGE_UNINSTALL => 'onPreUninstall',
            PackageEvents::POST_PACKAGE_INSTALL => 'record',
            PackageEvents::POST_PACKAGE_UPDATE => 'record',
            PackageEvents::POST_PACKAGE_UNINSTALL => 'onPostUninstall',
            PluginEvents::INIT => 'initAutoScripts',
            ComposerScriptEvents::POST_AUTOLOAD_DUMP => 'onPostAutoloadDump',
            ComposerScriptEvents::POST_INSTALL_CMD => 'onPostInstall',
            ComposerScriptEvents::POST_UPDATE_CMD => [['onPostUpdate', \PHP_INT_MAX], ['onPostUpdatePostMessages', ~\PHP_INT_MAX + 1]],
            ComposerScriptEvents::POST_CREATE_PROJECT_CMD => [
                ['onPostCreateProject', \PHP_INT_MAX],
                ['runSkeletonGenerator', \PHP_INT_MAX - 1],
                ['initAutoScripts', \PHP_INT_MAX - 2],
            ],
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
        // that way, we are sure to use all classes from the same version.
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(\dirname(__DIR__, 1), FilesystemIterator::SKIP_DOTS)) as $file) {
            /** @var SplFileInfo $file */
            if (\substr($file->getFilename(), -4) === '.php') {
                \class_exists(__NAMESPACE__ . \str_replace('/', '\\', \substr($file->getFilename(), \strlen(__DIR__), -4)));
            }
        }

        $this->container = new Container($composer, $io);

        if ($this->container->get(InputInterface::class) === null) {
            self::$activated = false;

            $io->writeError('<warning>Narrowspark Automatic has been disabled. No input object found on composer class.</warning>');

            return;
        }

        // overwrite composer instance
        $this->container->set(Composer::class, static function () use ($composer): Composer {
            return $composer;
        });

        /** @var \Composer\Installer\InstallationManager $installationManager */
        $installationManager = $composer->getInstallationManager();
        $installationManager->addInstaller($this->container->get(ConfiguratorInstaller::class));
        $installationManager->addInstaller($this->container->get(SkeletonInstaller::class));

        $this->container->get(Lock::class)->add('@readme', [
            'This file locks the automatic information of your project to a known state',
            'This file is @generated automatically',
        ]);

        $this->extendComposer(\debug_backtrace());

        $this->container->set(InstallationManager::class, static function (ContainerContract $container): InstallationManager {
            return new InstallationManager(
                $container->get(Composer::class),
                $container->get(IOInterface::class),
                $container->get(InputInterface::class)
            );
        });
    }

    /**
     * Executes on composer post-update event.
     */
    public function onPostUpdatePostMessages(Event $event): void
    {
        $this->container->get(IOInterface::class)->write($this->postMessages);
    }

    /**
     * Records composer operations.
     */
    public function record(PackageEvent $event): void
    {
        /** @var \Composer\DependencyResolver\Operation\InstallOperation|\Composer\DependencyResolver\Operation\UpdateOperation $operation */
        $operation = $event->getOperation();

        if ($operation instanceof UpdateOperation) {
            $package = $operation->getTargetPackage();
        } else {
            $package = $operation->getPackage();
        }

        if ($operation instanceof InstallOperation) {
            if ($this->container->get(Lock::class)->has($package->getName())) {
                return;
            }

            if ($package->getName() === self::PACKAGE_NAME) {
                \array_unshift($this->operations, $operation);

                return;
            }
        }

        $this->operations[] = $operation;
    }

    /**
     * Add auto-scripts to root composer.json.
     *
     * @throws Exception
     */
    public function initAutoScripts(): void
    {
        if (self::$isGlobalCommand) {
            return;
        }

        $scripts = $this->container->get(Composer::class)->getPackage()->getScripts();

        $autoScript = '@' . ScriptEvents::AUTO_SCRIPTS;

        if (isset($scripts[ComposerScriptEvents::POST_INSTALL_CMD], $scripts[ComposerScriptEvents::POST_UPDATE_CMD])
            && \in_array($autoScript, $scripts[ComposerScriptEvents::POST_INSTALL_CMD], true)
            && \in_array($autoScript, $scripts[ComposerScriptEvents::POST_UPDATE_CMD], true)
        ) {
            return;
        }

        [$json, $manipulator] = Util::getComposerJsonFileAndManipulator();

        if ((\is_countable($scripts) ? \count($scripts) : 0) === 0) {
            $manipulator->addMainKey('scripts', []);
        }

        $manipulator->addSubNode(
            'scripts',
            ComposerScriptEvents::POST_INSTALL_CMD,
            \array_merge($scripts[ComposerScriptEvents::POST_INSTALL_CMD] ?? [], [$autoScript])
        );
        $manipulator->addSubNode(
            'scripts',
            ComposerScriptEvents::POST_UPDATE_CMD,
            \array_merge($scripts[ComposerScriptEvents::POST_UPDATE_CMD] ?? [], [$autoScript])
        );

        if (! isset($scripts[ScriptEvents::AUTO_SCRIPTS])) {
            $manipulator->addSubNode('scripts', ScriptEvents::AUTO_SCRIPTS, new stdClass());
        }

        $this->container->get(Filesystem::class)->dumpFile($json->getPath(), $manipulator->getContents());

        $this->updateComposerLock();
    }

    /**
     * Executes on composer create project event.
     *
     * @throws Exception
     */
    public function onPostCreateProject(Event $event): void
    {
        if (self::$isGlobalCommand) {
            return;
        }

        [$json, $manipulator] = Util::getComposerJsonFileAndManipulator();

        // new projects are most of the time proprietary
        $manipulator->addMainKey('license', 'proprietary');

        // 'name' and 'description' are only required for public packages
        $manipulator->removeProperty('name');
        $manipulator->removeProperty('description');

        foreach ($this->container->get('composer-extra') as $key => $value) {
            if ($key !== self::COMPOSER_EXTRA_KEY) {
                $manipulator->addSubNode('extra', $key, $value);
            }
        }

        $this->container->get(Filesystem::class)->dumpFile($json->getPath(), $manipulator->getContents());

        $this->updateComposerLock();
    }

    /**
     * Run found skeleton generators.
     *
     * @throws Exception
     */
    public function runSkeletonGenerator(Event $event): void
    {
        if (self::$isGlobalCommand) {
            return;
        }

        /** @var \Narrowspark\Automatic\Lock $lock */
        $lock = $this->container->get(Lock::class);

        $lock->read();

        if ($lock->has(SkeletonInstaller::LOCK_KEY)) {
            $this->operations = [];

            $skeletonGenerator = new SkeletonGenerator(
                $this->container->get(IOInterface::class),
                $this->container->get(InstallationManager::class),
                $lock,
                $this->container->get('vendor-dir'),
                $this->container->get('composer-extra')
            );

            $skeletonGenerator->run();
            $skeletonGenerator->selfRemove();
        } else {
            $lock->reset();
        }
    }

    /**
     * Executes on composer pre-uninstall event.
     */
    public function onPreUninstall(PackageEvent $event): void
    {
        /** @var \Composer\DependencyResolver\Operation\UninstallOperation $operation */
        $operation = $event->getOperation();

        if ($this->isDevPackage($event, $operation->getPackage()->getName())) {
            return;
        }

        /** @var \Narrowspark\Automatic\Operation\Uninstall $uninstall */
        $uninstall = $this->container->get(Uninstall::class);

        if ($uninstall->supports($operation)) {
            $package = $uninstall->resolve($operation);

            $this->uninstallOperations[] = $package->getName();

            $uninstall->transform($package);
        }
    }

    /**
     * Executes on composer post-uninstall event.
     *
     * @throws Exception
     */
    public function onPostUninstall(PackageEvent $event): void
    {
        if ($this->isDevPackage($event, self::PACKAGE_NAME)) {
            return;
        }

        /** @var \Composer\DependencyResolver\Operation\UninstallOperation $operation */
        $operation = $event->getOperation();

        if ($operation->getPackage()->getName() === self::PACKAGE_NAME) {
            $scripts = $this->container->get(Composer::class)->getPackage()->getScripts();

            if ((\is_countable($scripts) ? \count($scripts) : 0) === 0) {
                return;
            }

            [$json, $manipulator] = Util::getComposerJsonFileAndManipulator();

            foreach ((array) $scripts[ComposerScriptEvents::POST_INSTALL_CMD] as $key => $script) {
                if ($script === '@' . ScriptEvents::AUTO_SCRIPTS) {
                    unset($scripts[ComposerScriptEvents::POST_INSTALL_CMD][$key]);
                }
            }

            $manipulator->addSubNode('scripts', ComposerScriptEvents::POST_INSTALL_CMD, $scripts[ComposerScriptEvents::POST_INSTALL_CMD]);

            foreach ((array) $scripts[ComposerScriptEvents::POST_UPDATE_CMD] as $key => $script) {
                if ($script === '@' . ScriptEvents::AUTO_SCRIPTS) {
                    unset($scripts[ComposerScriptEvents::POST_UPDATE_CMD][$key]);
                }
            }

            $manipulator->addSubNode('scripts', ComposerScriptEvents::POST_UPDATE_CMD, $scripts[ComposerScriptEvents::POST_UPDATE_CMD]);

            $this->container->get(Filesystem::class)->dumpFile($json->getPath(), $manipulator->getContents());

            $this->updateComposerLock();
        }
    }

    /**
     * Executes on composer autoload dump event.
     *
     * Load configurators from "automatic-configurator".
     *
     * @throws ReflectionException
     */
    public function onPostAutoloadDump(Event $event): void
    {
        /** @var \Narrowspark\Automatic\Configurator $configurator */
        $configurator = $this->container->get(ConfiguratorContract::class);

        if (self::$configuratorsLoaded) {
            $configurator->reset();
        }

        $lock = $this->container->get(Lock::class);
        $vendorDir = $this->container->get('vendor-dir');
        $classMap = (array) $lock->get(self::LOCK_CLASSMAP);

        foreach ((array) $lock->get(ConfiguratorInstaller::LOCK_KEY) as $packageName => $classList) {
            foreach ($classMap[$packageName] as $class => $path) {
                if (! \class_exists($class)) {
                    require_once \str_replace('%vendor_path%', $vendorDir, $path);
                }
            }

            /** @var \Narrowspark\Automatic\Common\Configurator\AbstractConfigurator $class */
            foreach ($classList as $class) {
                $reflectionClass = new ReflectionClass($class);

                if ($reflectionClass->isInstantiable() && $reflectionClass->hasMethod('getName')) {
                    $configurator->add($class::getName(), $reflectionClass->getName());
                }
            }
        }

        self::$configuratorsLoaded = true;
    }

    /**
     * Executes on composer install event.
     *
     * @throws Exception
     */
    public function onPostInstall(Event $event): void
    {
        $this->onPostUpdate($event);
    }

    /**
     * Executes on composer update event.
     *
     * @throws Exception
     */
    public function onPostUpdate(Event $event, array $operations = []): void
    {
        if (\count($operations) !== 0) {
            $this->operations = $operations;
        }

        /** @var \Narrowspark\Automatic\Lock $lock */
        $lock = $this->container->get(Lock::class);
        /** @var \Composer\IO\IOInterface $io */
        $io = $this->container->get(IOInterface::class);
        /** @var \Narrowspark\Automatic\Operation\Install $install */
        $install = $this->container->get(Install::class);
        /** @var \Narrowspark\Automatic\Common\Contract\Package[] $packages */
        $packages = [];

        foreach ($this->operations as $operation) {
            if ($install->supports($operation)) {
                $packages[] = $install->resolve($operation);
            }
        }

        $count = \count($packages) + \count($this->uninstallOperations);

        $io->writeError(\sprintf(
            '<info>Automatic operations: %s package%s</info>',
            $count,
            $count > 1 ? 's' : ''
        ));

        if (\count($packages) !== 0) {
            $automaticOptions = $this->container->get('composer-extra')[self::COMPOSER_EXTRA_KEY];
            $allowInstall = $automaticOptions['allow-auto-install'] ?? false;

            foreach ($packages as $package) {
                $prettyName = $package->getPrettyName();

                if (isset($automaticOptions['dont-discover']) && \array_key_exists($package->getName(), $automaticOptions['dont-discover'])) {
                    $io->write(\sprintf('<info>Package "%s" was ignored.</info>', $prettyName));

                    return;
                }

                if ($allowInstall === false && $package->getOperation() === PackageContract::INSTALL_OPERATION) {
                    $answer = $io->askAndValidate(
                        QuestionFactory::getPackageQuestion($prettyName, $package->getUrl()),
                        [QuestionFactory::class, 'validatePackageQuestionAnswer'],
                        null,
                        'n'
                    );

                    if ($answer === 'n') {
                        return;
                    }

                    if ($answer === 'a') {
                        $allowInstall = true;
                    } elseif ($answer === 'p') {
                        $allowInstall = true;

                        $this->manipulateComposerJsonWithAllowAutoInstall();

                        $this->shouldUpdateComposerLock = true;
                    }
                }

                $io->writeError(\sprintf('  - Configuring %s', $package->getName()));

                $install->transform($package);

                if ($package->hasConfig('post-install-output')) {
                    foreach ((array) $package->getConfig('post-install-output') as $line) {
                        $this->postMessages[] = self::expandTargetDir($this->container->get('composer-extra'), $line);
                    }

                    $this->postMessages[] = '';
                }
            }
        }

        if (\count($this->uninstallOperations) !== 0) {
            foreach ($this->uninstallOperations as $name) {
                $io->writeError(\sprintf('  - Unconfiguring %s', $name));
            }
        }

        if ($count !== 0) {
            \array_unshift(
                $this->postMessages,
                '',
                '<info>Some files may have been created or updated to configure your new packages.</info>',
                'Please <comment>review</comment>, <comment>edit</comment> and <comment>commit</comment> them: these files are <comment>yours</comment>',
                "\nTo show the package suggests run <comment>composer suggests</comment>"
            );
        }

        $io->writeError('<info>Writing automatic lock file</info>');

        $lock->write();

        if ($this->shouldUpdateComposerLock) {
            $this->updateComposerLock();
        }
    }

    /**
     * Executes on composer auto-scripts event.
     *
     * @throws ReflectionException
     */
    public function executeAutoScripts(Event $event): void
    {
        // force reloading scripts as we might have added and removed during this run
        $json = new JsonFile(Factory::getComposerFile());
        $jsonContents = $json->read();

        if (! isset($jsonContents['scripts'][ScriptEvents::AUTO_SCRIPTS])) {
            $this->container->get(IOInterface::class)->write('No auto-scripts section was found under scripts', true, IOInterface::VERBOSE);

            return;
        }

        if (\in_array(true, \array_map('\is_numeric', \array_keys($jsonContents['scripts'][ScriptEvents::AUTO_SCRIPTS])), true)) {
            return;
        }

        $event->stopPropagation();

        /** @var \Narrowspark\Automatic\ScriptExecutor $scriptExecutor */
        $scriptExecutor = $this->container->get(ScriptExecutor::class);

        foreach ((array) $this->container->get(Lock::class)->get(ScriptExecutor::TYPE) as $extenders) {
            foreach ($extenders as $class => $path) {
                if (! \class_exists($class)) {
                    require_once $path;
                }

                $reflectionClass = new ReflectionClass($class);

                if ($reflectionClass->isInstantiable() && $reflectionClass->hasMethod('getType')) {
                    $scriptExecutor->add($class::getType(), $class);
                }
            }
        }

        foreach ($jsonContents['scripts'][ScriptEvents::AUTO_SCRIPTS] as $cmd => $type) {
            $scriptExecutor->execute($type, $cmd);
        }
    }

    /**
     * Check if package is in require-dev.
     * When Composer runs with --no-dev, ignore uninstall operations on packages from require-dev.
     *
     * @param \Composer\Installer\PackageEvent|\Composer\Script\Event $event
     */
    private function isDevPackage($event, string $packageName): bool
    {
        if (! $event->isDevMode()) {
            foreach ($event->getComposer()->getLocker()->getLockData()['packages-dev'] as $devPackage) {
                if ($devPackage['name'] === $packageName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Update composer.lock file with the composer.json change.
     *
     * @throws Exception
     */
    private function updateComposerLock(): void
    {
        $composerLockPath = Util::getComposerLockFile();
        $composerJson = \file_get_contents(Factory::getComposerFile());
        $composer = $this->container->get(Composer::class);

        $lockFile = new JsonFile($composerLockPath, null, $this->container->get(IOInterface::class));
        $locker = new Locker(
            $this->container->get(IOInterface::class),
            $lockFile,
            $composer->getRepositoryManager(),
            $composer->getInstallationManager(),
            (string) $composerJson
        );

        $lockData = $locker->getLockData();
        $lockData['content-hash'] = Locker::getContentHash((string) $composerJson);

        $lockFile->write($lockData);
    }

    /**
     * Add extra option "allow-auto-install" to composer.json.
     *
     * @throws InvalidArgumentException
     */
    private function manipulateComposerJsonWithAllowAutoInstall(): void
    {
        [$json, $manipulator] = Util::getComposerJsonFileAndManipulator();

        $manipulator->addSubNode('extra', 'automatic.allow-auto-install', true);

        $this->container->get(Filesystem::class)->dumpFile($json->getPath(), $manipulator->getContents());
    }

    /**
     * Check if automatic can be activated.
     */
    private function getErrorMessage(IOInterface $io): ?string
    {
        // @codeCoverageIgnoreStart
        if (! extension_loaded('openssl')) {
            return 'You must enable the openssl extension in your [php.ini] file';
        }

        if (\version_compare(Util::getComposerVersion(), '1.8.0', '<')) {
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
     * Extend the composer object with some automatic settings.
     *
     * @param array<int|string, mixed> $backtrace
     */
    private function extendComposer(array $backtrace): void
    {
        foreach ($backtrace as $trace) {
            if (isset($trace['object']) && $trace['object'] instanceof Installer) {
                /** @var \Composer\Installer $installer */
                $installer = $trace['object'];
                $installer->setSuggestedPackagesReporter(new SuggestedPackagesReporter(new NullIO()));

                break;
            }
        }

        foreach ($backtrace as $trace) {
            if (! isset($trace['object']) || ! isset($trace['args'][0])) {
                continue;
            }

            if ($trace['object'] instanceof GlobalCommand) {
                self::$isGlobalCommand = true;
            }

            if (! $trace['object'] instanceof Application || ! $trace['args'][0] instanceof ArgvInput) {
                continue;
            }

            /** @var \Symfony\Component\Console\Input\InputInterface $input */
            $input = $trace['args'][0];
            $app = $trace['object'];

            try {
                /** @var null|string $command */
                $command = $input->getFirstArgument();
                $command = $command !== null ? $app->find($command)->getName() : null;
            } catch (InvalidArgumentException $e) {
                $command = null;
            }

            if ($command === 'create-project') {
                if (\version_compare(Util::getComposerVersion(), '1.7.0', '>=')) {
                    $input->setOption('remove-vcs', true);
                } else {
                    $input->setInteractive(false);
                }
            } elseif ($command === 'suggests') {
                $input->setOption('by-package', true);
            }

            if ($input->hasOption('no-suggest')) {
                $input->setOption('no-suggest', true);
            }

            break;
        }
    }
}
