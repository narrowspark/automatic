<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Closure;
use Composer\Composer;
use Composer\Config;
use Composer\Console\Application;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Pool;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Installer\SuggestedPackagesReporter;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Json\JsonFile;
use Composer\Package\BasePackage;
use Composer\Package\Locker;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Repository\ComposerRepository as BaseComposerRepository;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use FilesystemIterator;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Exception\RuntimeException;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Traits\ExpandTargetDirTrait;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Common\Util;
use Narrowspark\Automatic\Contract\Container as ContractContainer;
use Narrowspark\Automatic\Installer\ConfiguratorInstaller;
use Narrowspark\Automatic\Installer\InstallationManager;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\Prefetcher\ParallelDownloader;
use Narrowspark\Automatic\Prefetcher\Prefetcher;
use Narrowspark\Automatic\Prefetcher\TruncatedComposerRepository;
use Narrowspark\Automatic\Security\Command\AuditCommandProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;

class Automatic implements PluginInterface, EventSubscriberInterface, Capable
{
    use ExpandTargetDirTrait;
    use GetGenericPropertyReaderTrait;

    public const VERSION = '0.5.0';

    /**
     * @var string
     */
    public const LOCK_CLASSMAP = 'classmap';

    /**
     * @var string
     */
    public const LOCK_PACKAGES = 'packages';

    /**
     * @var string
     */
    public const PACKAGE_NAME = 'narrowspark/automatic';

    /**
     * A Container instance.
     *
     * @var \Narrowspark\Automatic\Contract\Container
     */
    protected $container;

    /**
     * Check if the the plugin is activated.
     *
     * @var bool
     */
    private static $activated = true;

    /**
     * Check if composer.lock should be updated.
     *
     * @var bool
     */
    private $shouldUpdateComposerLock = false;

    /**
     * The composer operations.
     *
     * @var array
     */
    private $operations = [];

    /**
     * @var array
     */
    private $postInstallOutput = [''];

    /**
     * Get the Container instance.
     *
     * @return \Narrowspark\Automatic\Contract\Container
     */
    public function getContainer(): ContractContainer
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
            'auto-scripts'                             => 'executeAutoScripts',
            'post-install-out'                         => 'postInstallOut',
            InstallerEvents::PRE_DEPENDENCIES_SOLVING  => [['onPreDependenciesSolving', \PHP_INT_MAX]],
            InstallerEvents::POST_DEPENDENCIES_SOLVING => [['populateFilesCacheDir', \PHP_INT_MAX]],
            PackageEvents::PRE_PACKAGE_INSTALL         => [['populateFilesCacheDir', ~\PHP_INT_MAX]],
            PackageEvents::PRE_PACKAGE_UPDATE          => [['populateFilesCacheDir', ~\PHP_INT_MAX]],
            PackageEvents::POST_PACKAGE_INSTALL        => 'record',
            PackageEvents::POST_PACKAGE_UPDATE         => 'record',
            PackageEvents::POST_PACKAGE_UNINSTALL      => 'record',
            PluginEvents::PRE_FILE_DOWNLOAD            => 'onFileDownload',
            ScriptEvents::POST_INSTALL_CMD             => 'onPostInstall',
            ScriptEvents::POST_UPDATE_CMD              => 'onPostUpdate',
            ScriptEvents::POST_CREATE_PROJECT_CMD      => [['onPostCreateProject', \PHP_INT_MAX], ['runSkeletonGenerator']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => AuditCommandProvider::class,
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
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(\dirname(__DIR__, 1), FilesystemIterator::SKIP_DOTS)) as $file) {
            /** @var \SplFileInfo $file */
            if (\mb_substr($file->getFilename(), -4) === '.php') {
                require_once $file;
            }
        }

        $this->container = new Container($composer, $io);

        /** @var \Composer\Installer\InstallationManager $installationManager */
        $installationManager = $this->container->get(Composer::class)->getInstallationManager();
        $installationManager->addInstaller($this->container->get(ConfiguratorInstaller::class));
        $installationManager->addInstaller($this->container->get(SkeletonInstaller::class));

        /** @var \Narrowspark\Automatic\LegacyTagsManager $tagsManager */
        $tagsManager = $this->container->get(LegacyTagsManager::class);

        $this->configureLegacyTagsManager($io, $tagsManager);

        $composer->setRepositoryManager($this->extendRepositoryManager($composer, $io, $tagsManager));

        $this->container->get(Lock::class)->add('@readme', [
            'This file locks the automatic information of your project to a known state',
            'This file is @generated automatically',
        ]);

        $this->extendComposer(\debug_backtrace());

        $this->container->set(InstallationManager::class, static function (Container $container) use ($composer) {
            return new InstallationManager(
                $composer,
                $container->get(IOInterface::class),
                $container->get(InputInterface::class)
            );
        });
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

        $this->container->get(IOInterface::class)->write($this->postInstallOutput);
    }

    /**
     * Records composer operations.
     *
     * @param \Composer\Installer\PackageEvent $event
     *
     * @return void
     */
    public function record(PackageEvent $event): void
    {
        if (! $this->shouldRecordOperation($event)) {
            return;
        }

        $operation = $event->getOperation();

        if ($operation instanceof InstallOperation && $operation->getPackage()->getName() === self::PACKAGE_NAME) {
            \array_unshift($this->operations, $operation);
        } else {
            $this->operations[] = $operation;
        }
    }

    /**
     * Execute on composer create project event.
     *
     * @param \Composer\Script\Event $event
     *
     * @throws \Exception
     */
    public function onPostCreateProject(Event $event): void
    {
        /** @var \Composer\Json\JsonFile $json */
        /** @var \Composer\Json\JsonManipulator $manipulator */
        [$json, $manipulator] = Util::getComposerJsonFileAndManipulator();

        // new projects are most of the time proprietary
        $manipulator->addMainKey('license', 'proprietary');

        // 'name' and 'description' are only required for public packages
        $manipulator->removeProperty('name');
        $manipulator->removeProperty('description');

        foreach ($this->container->get('composer-extra') as $key => $value) {
            if ($key !== Util::COMPOSER_EXTRA_KEY) {
                $manipulator->addSubNode('extra', $key, $value);
            }
        }

        $manipulator->addSubNode('scripts', 'post-install-out', 'This key is needed for Narrowspark Automatic to show package messages.');

        $scripts = [
            '@auto-scripts',
            '@post-install-out',
        ];

        $manipulator->addSubNode('scripts', 'post-install-cmd', $scripts);
        $manipulator->addSubNode('scripts', 'post-update-cmd', $scripts);
        $manipulator->addSubNode('scripts', 'auto-scripts', new \stdClass());

        \file_put_contents($json->getPath(), $manipulator->getContents());

        $this->updateComposerLock();
    }

    /**
     * Run found skeleton generators.
     *
     * @param \Composer\Script\Event $event
     *
     * @throws \Exception
     *
     * @return void
     */
    public function runSkeletonGenerator(Event $event): void
    {
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
     * Execute on composer install event.
     *
     * @param \Composer\Script\Event $event
     *
     * @throws \Exception
     *
     * @return void
     */
    public function onPostInstall(Event $event): void
    {
        $this->onPostUpdate($event);
    }

    /**
     * Execute on composer update event.
     *
     * @param \Composer\Script\Event $event
     * @param array                  $operations
     *
     * @throws \Exception
     *
     * @return void
     */
    public function onPostUpdate(Event $event, array $operations = []): void
    {
        if (\count($operations) !== 0) {
            $this->operations = $operations;
        }

        /** @var \Narrowspark\Automatic\Lock $lock */
        /** @var \Composer\IO\IOInterface $io */
        $automaticOptions = $this->container->get('composer-extra')[Util::COMPOSER_EXTRA_KEY];
        $allowInstall     = $automaticOptions['allow-auto-install'] ?? false;
        $packages         = $this->container->get(OperationsResolver::class)->resolve($this->operations);
        $lock             = $this->container->get(Lock::class);
        $io               = $this->container->get(IOInterface::class);

        $io->writeError(\sprintf(
            '<info>Automatic operations: %s package%s</info>',
            \count($packages),
            \count($packages) > 1 ? 's' : ''
        ));

        $classMap = (array) $lock->get(self::LOCK_CLASSMAP);

        foreach ((array) $lock->get(ConfiguratorInstaller::LOCK_KEY) as $packageName => $classList) {
            foreach ($classMap[$packageName] as $class => $path) {
                if (! \class_exists($class)) {
                    require_once \str_replace('%vendor_path%', $this->container->get('vendor-dir'), $path);
                }
            }

            /** @var \Narrowspark\Automatic\Common\Configurator\AbstractConfigurator $class */
            foreach ($classList as $class) {
                $reflectionClass = new ReflectionClass($class);

                if ($reflectionClass->isInstantiable() && $reflectionClass->hasMethod('getName')) {
                    $this->container->get(Configurator::class)->add($class::getName(), $reflectionClass->getName());
                }
            }
        }

        /** @var \Narrowspark\Automatic\Common\Contract\Package $package */
        foreach ($packages as $package) {
            $prettyName = $package->getPrettyName();

            if (isset($automaticOptions['dont-discover']) && \array_key_exists($prettyName, $automaticOptions['dont-discover'])) {
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

            $this->doActionOnPackageOperation($package);
        }

        if (\count($packages) !== 0) {
            \array_unshift(
                $this->postInstallOutput,
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
     * Execute on composer auto-scripts event.
     *
     * @param \Composer\Script\Event $event
     *
     * @return void
     */
    public function executeAutoScripts(Event $event): void
    {
        $event->stopPropagation();

        // force reloading scripts as we might have added and removed during this run
        $json         = new JsonFile(Factory::getComposerFile());
        $jsonContents = $json->read();

        if (isset($jsonContents['scripts']['auto-scripts'])) {
            /** @var \Narrowspark\Automatic\ScriptExecutor $scriptExecutor */
            $scriptExecutor = $this->container->get(ScriptExecutor::class);

            foreach ((array) $this->container->get(Lock::class)->get(ScriptExecutor::TYPE) as $name => $extenders) {
                foreach ($extenders as $class => $path) {
                    if (! \class_exists($class)) {
                        require_once $path;
                    }

                    $scriptExecutor->addExtender($class::getType(), $class);
                }
            }

            foreach ($jsonContents['scripts']['auto-scripts'] as $cmd => $type) {
                $scriptExecutor->execute($type, $cmd);
            }
        } else {
            $this->container->get(IOInterface::class)->write('No auto-scripts section was found under scripts', true, IOInterface::VERBOSE);
        }
    }

    /**
     * Populate the provider cache.
     *
     * @param \Composer\Installer\InstallerEvent $event
     *
     * @return void
     */
    public function onPreDependenciesSolving(InstallerEvent $event): void
    {
        $listed   = [];
        $packages = [];
        $pool     = $event->getPool();
        $pool     = \Closure::bind(function () {
            foreach ($this->providerRepos as $k => $repo) {
                $this->providerRepos[$k] = new class($repo) extends BaseComposerRepository {
                    /**
                     * A repository implementation.
                     *
                     * @var \Composer\Repository\RepositoryInterface
                     */
                    private $repo;

                    /**
                     * @param \Composer\Repository\RepositoryInterface $repo
                     */
                    public function __construct(RepositoryInterface $repo)
                    {
                        $this->repo = $repo;
                    }

                    /**
                     * {@inheritdoc}
                     */
                    public function whatProvides(Pool $pool, $name, $bypassFilters = false)
                    {
                        $packages = [];

                        if (! \method_exists($this->repo, 'whatProvides')) {
                            return $packages;
                        }

                        foreach ($this->repo->whatProvides($pool, $name, $bypassFilters) as $k => $p) {
                            $packages[$k] = clone $p;
                        }

                        return $packages;
                    }
                };
            }

            return $this;
        }, clone $pool, $pool)();

        foreach ($event->getRequest()->getJobs() as $job) {
            if ($job['cmd'] !== 'install' || \mb_strpos($job['packageName'], '/') === false) {
                continue;
            }

            $listed[$job['packageName']] = true;
            $packages[]                  = [$job['packageName'], $job['constraint']];
        }

        $this->container->get(ParallelDownloader::class)->download($packages, function ($packageName, $constraint) use (&$listed, &$packages, $pool): void {
            /** @var \Composer\Package\PackageInterface $package */
            foreach ($pool->whatProvides($packageName, $constraint, true) as $package) {
                /** @var \Composer\Package\Link $link */
                foreach (\array_merge($package->getRequires(), $package->getConflicts(), $package->getReplaces()) as $link) {
                    if (isset($listed[$link->getTarget()]) || \mb_strpos($link->getTarget(), '/') === false) {
                        continue;
                    }

                    $listed[$link->getTarget()] = true;
                    $packages[]                 = [$link->getTarget(), $link->getConstraint()];
                }
            }
        });
    }

    /**
     * Wrapper for the fetchAllFromOperations function.
     *
     * @see \Narrowspark\Automatic\Prefetcher\Prefetcher::fetchAllFromOperations()
     *
     * @param \Composer\Installer\InstallerEvent $event
     *
     * @return void
     */
    public function populateFilesCacheDir(InstallerEvent $event): void
    {
        $this->container->get(Prefetcher::class)->fetchAllFromOperations($event);
    }

    /**
     * Adds the parallel downloader to composer.
     *
     * @param \Composer\Plugin\PreFileDownloadEvent $event
     *
     * @return void
     */
    public function onFileDownload(PreFileDownloadEvent $event): void
    {
        /** @var \Narrowspark\Automatic\Prefetcher\ParallelDownloader $rfs */
        $rfs = $this->container->get(ParallelDownloader::class);

        if ($event->getRemoteFilesystem() !== $rfs) {
            $event->setRemoteFilesystem($rfs->setNextOptions($event->getRemoteFilesystem()->getOptions()));
        }
    }

    /**
     * Add found legacy tags to the tags manager.
     *
     * @param \Composer\IO\IOInterface                 $io
     * @param array                                    $requires
     * @param \Narrowspark\Automatic\LegacyTagsManager $tagsManager
     *
     * @return void
     */
    private function addLegacyTags(IOInterface $io, array $requires, LegacyTagsManager $tagsManager): void
    {
        foreach ($requires as $name => $version) {
            if (\is_int($name)) {
                $io->writeError(\sprintf('Constrain [%s] skipped, because package name is a number [%s]', $version, $name));

                continue;
            }

            if (\mb_strpos($name, '/') === false) {
                $io->writeError(\sprintf('Constrain [%s] skipped, package name [%s] without a slash is not supported', $version, $name));

                continue;
            }

            $tagsManager->addConstraint($name, $version);
        }
    }

    /**
     * Check which package should be recorded.
     *
     * @param \Composer\Installer\PackageEvent $event
     *
     * @return bool
     */
    private function shouldRecordOperation(PackageEvent $event): bool
    {
        $operation = $event->getOperation();

        if ($operation instanceof UpdateOperation) {
            $package = $operation->getTargetPackage();
        } else {
            $package = $operation->getPackage();
        }

        // when Composer runs with --no-dev, ignore uninstall operations on packages from require-dev
        if ($operation instanceof UninstallOperation && ! $event->isDevMode()) {
            foreach ($event->getComposer()->getLocker()->getLockData()['packages-dev'] as $devPackage) {
                if ($package->getName() === $devPackage['name']) {
                    return false;
                }
            }
        }

        $isInstallOperation = $operation instanceof InstallOperation && ! $this->container->get(Lock::class)->has($package->getName());

        return $isInstallOperation || $operation instanceof UninstallOperation;
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
        $composerLockPath = \mb_substr(Factory::getComposerFile(), 0, -4) . 'lock';
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

    /**
     * Add extra option "allow-auto-install" to composer.json.
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    private function manipulateComposerJsonWithAllowAutoInstall(): void
    {
        /** @var \Composer\Json\JsonFile $json */
        /** @var \Composer\Json\JsonManipulator $manipulator */
        [$json, $manipulator] = Util::getComposerJsonFileAndManipulator();

        $manipulator->addSubNode('extra', 'automatic.allow-auto-install', true);

        \file_put_contents($json->getPath(), $manipulator->getContents());
    }

    /**
     * Choose action on package operation.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     *
     * @throws \Exception
     *
     * @return void
     */
    private function doActionOnPackageOperation(PackageContract $package): void
    {
        /** @var \Narrowspark\Automatic\ClassFinder $classFinder */
        $classFinder  = $this->container->get(ClassFinder::class);
        $foundClasses = $classFinder->setComposerAutoload($package->getName(), $package->getAutoload())
            ->setFilter(function (\SplFileInfo $fileInfo) use ($package) {
                return \mb_strpos((string) \mb_strstr($fileInfo->getPathname(), $package->getName()), '/Automatic/') !== false;
            })
            ->find()
            ->getAll();

        foreach ($foundClasses as $class => $path) {
            if (! \class_exists($class)) {
                require_once $path;
            }
        }

        /** @var \Narrowspark\Automatic\PackageConfigurator $packageConfigurator */
        $packageConfigurator = $this->container->get(PackageConfigurator::class);

        if ($package->hasConfig(PackageConfigurator::TYPE)) {
            foreach ((array) $package->getConfig(PackageConfigurator::TYPE) as $name => $configurator) {
                $packageConfigurator->add($name, $configurator);
            }
        }

        $io = $this->container->get(IOInterface::class);

        if ($package->getOperation() === 'install') {
            $io->writeError(\sprintf('  - Configuring %s', $package->getName()));

            $this->doInstall($package, $packageConfigurator, $foundClasses);
        } elseif ($package->getOperation() === 'uninstall') {
            $io->writeError(\sprintf('  - Unconfiguring %s', $package->getName()));

            $this->doUninstall($package, $packageConfigurator);
        }

        $packageConfigurator->reset();
        $classFinder->reset();
    }

    /**
     * All package configuration and installations happens here.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     * @param \Narrowspark\Automatic\PackageConfigurator     $packageConfigurator
     * @param array                                          $foundClasses
     *
     * @throws \Exception
     *
     * @return void
     */
    private function doInstall(
        PackageContract $package,
        PackageConfigurator $packageConfigurator,
        array $foundClasses
    ): void {
        /** @var \Narrowspark\Automatic\Lock $lock */
        $lock = $this->container->get(Lock::class);

        if ($package->hasConfig(ScriptExecutor::TYPE)) {
            $extenders = [];

            foreach ((array) $package->getConfig(ScriptExecutor::TYPE) as $extender) {
                if (isset($foundClasses[$extender])) {
                    $extenders[$extender] = $foundClasses[$extender];
                }
            }

            $lock->addSub(ScriptExecutor::TYPE, $package->getName(), $extenders);
        }

        /** @var \Narrowspark\Automatic\Configurator $configurator */
        $configurator = $this->container->get(Configurator::class);

        $configurator->configure($package);
        $packageConfigurator->configure($package);

        $this->showWarningOnRemainingConfigurators($package, $packageConfigurator, $configurator);

        if ($package->hasConfig('post-install-output')) {
            foreach ((array) $package->getConfig('post-install-output') as $line) {
                $this->postInstallOutput[] = self::expandTargetDir($this->container->get('composer-extra'), $line);
            }

            $this->postInstallOutput[] = '';
        }

        $lock->addSub(self::LOCK_PACKAGES, $package->getName(), $package->toArray());
    }

    /**
     * All package unconfiguration and uninstallations happens here.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     * @param \Narrowspark\Automatic\PackageConfigurator     $packageConfigurator
     *
     * @throws \Exception
     *
     * @return void
     */
    private function doUninstall(PackageContract $package, PackageConfigurator $packageConfigurator): void
    {
        /** @var \Narrowspark\Automatic\Configurator $configurator */
        $configurator = $this->container->get(Configurator::class);

        $configurator->unconfigure($package);
        $packageConfigurator->unconfigure($package);

        $this->showWarningOnRemainingConfigurators($package, $packageConfigurator, $configurator);

        /** @var \Narrowspark\Automatic\Lock $lock */
        $lock = $this->container->get(Lock::class);

        if ($package->hasConfig(ScriptExecutor::TYPE)) {
            $lock->remove(ScriptExecutor::TYPE, $package->getName());
        }

        $lock->remove(self::LOCK_PACKAGES, $package->getName());
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
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\RuntimeException
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
     * Configure the LegacyTagsManager with legacy package requires.
     *
     * @param \Composer\IO\IOInterface                 $io
     * @param \Narrowspark\Automatic\LegacyTagsManager $tagsManager
     *
     * @return void
     */
    private function configureLegacyTagsManager(IOInterface $io, LegacyTagsManager $tagsManager): void
    {
        $extra      = $this->container->get('composer-extra');
        $envRequire = \getenv('AUTOMATIC_REQUIRE');

        if ($envRequire !== false) {
            $requires = [];

            foreach (\explode(',', $envRequire) as $packageString) {
                [$packageName, $version] = \explode('=', $packageString, 2);

                $requires[$packageName] = $version;
            }

            $this->addLegacyTags($io, $requires, $tagsManager);
        } elseif (isset($extra[Util::COMPOSER_EXTRA_KEY]['require'])) {
            $this->addLegacyTags($io, $extra[Util::COMPOSER_EXTRA_KEY]['require'], $tagsManager);
        }
    }

    /**
     * Extend the composer object with some automatic settings.
     *
     * @param array $backtrace
     *
     * @return void
     */
    private function extendComposer($backtrace): void
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

            if (! $trace['object'] instanceof Application || ! $trace['args'][0] instanceof ArgvInput) {
                continue;
            }

            /** @var \Symfony\Component\Console\Input\InputInterface $input */
            $input = $trace['args'][0];
            $app   = $trace['object'];

            try {
                /** @var null|string $command */
                $command = $input->getFirstArgument();
                $command = $command !== null ? $app->find($command)->getName() : null;
            } catch (\InvalidArgumentException $e) {
                $command = null;
            }

            if ($command === 'create-project') {
                if (\version_compare(self::getComposerVersion(), '1.7.0', '>=')) {
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

            // When prefer-lowest is set and no stable version has been released,
            // we consider "dev" more stable than "alpha", "beta" or "RC". This
            // allows testing lowest versions with potential fixes applied.
            if ($input->hasParameterOption('--prefer-lowest', true)) {
                BasePackage::$stabilities['dev'] = 1 + BasePackage::STABILITY_STABLE;
            }
        }
    }

    /**
     * Extend the repository manager with a truncated composer repository.
     *
     * @param \Composer\Composer                       $composer
     * @param \Composer\IO\IOInterface                 $io
     * @param \Narrowspark\Automatic\LegacyTagsManager $tagsManager
     *
     * @return \Composer\Repository\RepositoryManager
     */
    private function extendRepositoryManager(
        Composer $composer,
        IOInterface $io,
        LegacyTagsManager $tagsManager
    ): RepositoryManager {
        $manager = RepositoryFactory::manager(
            $io,
            $this->container->get(Config::class),
            $this->container->get(Composer::class)->getEventDispatcher(),
            $this->container->get(ParallelDownloader::class)
        );

        $setRepositories = Closure::bind(function (RepositoryManager $manager) use ($tagsManager) {
            $manager->repositoryClasses = $this->repositoryClasses;
            $manager->setRepositoryClass('composer', TruncatedComposerRepository::class);
            $manager->repositories = $this->repositories;

            $i = 0;

            foreach (RepositoryFactory::defaultRepos(null, $this->config, $manager) as $repo) {
                $manager->repositories[$i++] = $repo;

                if ($repo instanceof TruncatedComposerRepository) {
                    $repo->setTagsManager($tagsManager);
                }
            }

            $manager->setLocalRepository($this->getLocalRepository());
        }, $composer->getRepositoryManager(), RepositoryManager::class);

        $setRepositories($manager);

        return $manager;
    }

    /**
     * Show a waring if remaining configurators are found in package config.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     * @param \Narrowspark\Automatic\PackageConfigurator     $packageConfigurator
     * @param \Narrowspark\Automatic\Configurator            $configurator
     *
     * @return void
     */
    private function showWarningOnRemainingConfigurators(
        PackageContract $package,
        PackageConfigurator $packageConfigurator,
        Configurator $configurator
    ): void {
        $packageConfigurators = \array_keys((array) $package->getConfig(ConfiguratorContract::TYPE));

        foreach (\array_keys($configurator->getConfigurators()) as $key) {
            if (isset($packageConfigurators[$key])) {
                unset($packageConfigurators[$key]);
            }
        }

        foreach (\array_keys($packageConfigurator->getConfigurators()) as $key) {
            if (isset($packageConfigurators[$key])) {
                unset($packageConfigurators[$key]);
            }
        }

        if (\count($packageConfigurators) !== 0) {
            $this->container->get(IOInterface::class)->writeError(\sprintf(
                '<warning>No configurators were run for [%s] in [%s]</warning>',
                \implode(', ', $packageConfigurators),
                $package->getPrettyName()
            ));
        }
    }
}
