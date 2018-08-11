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
use Composer\Package\Comparer\Comparer;
use Composer\Package\Locker;
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
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Contract\ScriptExtender as ScriptExtenderContract;
use Narrowspark\Automatic\Common\Traits\ExpandTargetDirTrait;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Common\Util;
use Narrowspark\Automatic\Contract\Container as ContractContainer;
use Narrowspark\Automatic\Installer\ConfiguratorInstaller;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\Prefetcher\ParallelDownloader;
use Narrowspark\Automatic\Prefetcher\Prefetcher;
use Narrowspark\Automatic\Prefetcher\TruncatedComposerRepository;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Component\Console\Input\ArgvInput;

class Automatic implements PluginInterface, EventSubscriberInterface
{
    use ExpandTargetDirTrait;
    use GetGenericPropertyReaderTrait;

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
            PluginEvents::COMMAND                      => 'onCommand',
            ScriptEvents::POST_INSTALL_CMD             => 'onPostInstall',
            ScriptEvents::POST_UPDATE_CMD              => 'onPostUpdate',
            ScriptEvents::POST_CREATE_PROJECT_CMD      => [['onPostCreateProject', \PHP_INT_MAX]],
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

        $manager = RepositoryFactory::manager(
            $this->container->get(IOInterface::class),
            $this->container->get(Config::class),
            $this->container->get(Composer::class)->getEventDispatcher(),
            $this->container->get(ParallelDownloader::class)
        );
        $setRepositories = Closure::bind(function (RepositoryManager $manager) {
            $manager->repositoryClasses = $this->repositoryClasses;
            $manager->setRepositoryClass('composer', TruncatedComposerRepository::class);
            $manager->repositories = $this->repositories;

            $i = 0;

            foreach (RepositoryFactory::defaultRepos(null, $this->config, $manager) as $repo) {
                $manager->repositories[$i++] = $repo;
            }

            $manager->setLocalRepository($this->getLocalRepository());
        }, $composer->getRepositoryManager(), RepositoryManager::class);

        $setRepositories($manager);

        $composer->setRepositoryManager($manager);

        $this->container->get(Lock::class)->add('@readme', [
            'This file locks the automatic information of your project to a known state',
            'This file is @generated automatically',
        ]);

        $backtrace = \debug_backtrace();

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
                $command = $command ? $app->find($command)->getName() : null;
            } catch (\InvalidArgumentException $e) {
                $command = null;
            }

            if ($command === 'create-project') {
                // detect Composer >=1.7 (using the Composer::VERSION constant doesn't work with snapshot builds)
                if (\class_exists(Comparer::class)) {
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
        }
    }

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

        $manipulator->addSubNode('scripts', 'post-install-out', 'Added by automatic');

        $scripts = [
            '@auto-scripts',
            '@post-install-out',
        ];

        $manipulator->addSubNode('scripts', 'post-install-cmd', $scripts);
        $manipulator->addSubNode('scripts', 'post-update-cmd', $scripts);
        $manipulator->addSubNode('scripts', 'auto-scripts', new \stdClass());

        \file_put_contents($json->getPath(), $manipulator->getContents());

        $this->updateComposerLock();

        $this->runSkeletonGenerator();
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

        $configuratorsClassmap = (array) $lock->get(self::LOCK_CLASSMAP);

        foreach ((array) $lock->get(ConfiguratorInstaller::LOCK_KEY) as $packageName => $classList) {
            foreach ($configuratorsClassmap[$packageName] as $path) {
                include \str_replace('%vendor_path%', $this->container->get('vendor-dir'), $path);
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
                '<comment>The automatic.lock file has all information about the installed packages.</comment>',
                'Please <comment>review</comment>, <comment>edit</comment> and <comment>commit</comment> them: these files are <comment>yours</comment>.',
                "\nTo show the package suggests run <comment>composer suggests</comment>."
            );
        }

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

            foreach ((array) $this->container->get(Lock::class)->get(ScriptExecutor::TYPE) as $extender) {
                $scriptExecutor->addExtender($extender);
            }

            foreach ($jsonContents['scripts']['auto-scripts'] as $cmd => $type) {
                $scriptExecutor->execute($type, $cmd);
            }
        } else {
            $this->container->get(IOInterface::class)->write('No auto-scripts section was found under scripts.', true, IOInterface::VERBOSE);
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

            $this->doInstall($package, $packageConfigurator);
        } elseif ($package->getOperation() === 'uninstall') {
            $io->writeError(\sprintf('  - Unconfiguring %s', $package->getName()));

            $this->doUninstall($package, $packageConfigurator);
        }

        $packageConfigurator->clear();
    }

    /**
     * All package configuration and installations happens here.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     * @param \Narrowspark\Automatic\PackageConfigurator     $packageConfigurator
     *
     * @throws \Exception
     *
     * @return void
     */
    private function doInstall(PackageContract $package, PackageConfigurator $packageConfigurator): void
    {
        /** @var \Narrowspark\Automatic\Lock $lock */
        $lock = $this->container->get(Lock::class);

        $this->writeScriptExtenderToLock($package, $lock);

        $this->container->get(Configurator::class)->configure($package);

        $packageConfigurator->configure($package);

        if ($package->hasConfig('post-install-output')) {
            foreach ((array) $package->getConfig('post-install-output') as $line) {
                $this->postInstallOutput[] = self::expandTargetDir($this->container->get('composer-extra'), $line);
            }

            $this->postInstallOutput[] = '';
        }

        $lock->add(
            self::LOCK_PACKAGES,
            \array_merge(
                (array) $lock->get(self::LOCK_PACKAGES),
                [$package->getName() => $package->toArray()]
            )
        );
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
        $this->container->get(Configurator::class)->unconfigure($package);

        $packageConfigurator->unconfigure($package);

        /** @var \Narrowspark\Automatic\Lock $lock */
        $lock = $this->container->get(Lock::class);

        if ($package->hasConfig(ScriptExecutor::TYPE)) {
            $extenders = (array) $lock->get(ScriptExecutor::TYPE);

            /** @var \Narrowspark\Automatic\Common\Contract\ScriptExtender $extender */
            foreach ((array) $package->getConfig(ScriptExecutor::TYPE) as $extender) {
                $type = $extender::getType();

                if (isset($extenders[$type])) {
                    unset($extenders[$type]);
                }
            }

            $lock->add(ScriptExecutor::TYPE, $extenders);
        }

        $lock->remove($package->getName());
    }

    /**
     * Looks if the package has a extra section for script extender.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     * @param \Narrowspark\Automatic\Lock                    $lock
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException
     *
     * @return void
     */
    private function writeScriptExtenderToLock(PackageContract $package, Lock $lock): void
    {
        if ($package->hasConfig(ScriptExecutor::TYPE)) {
            $extenders = (array) $lock->get(ScriptExecutor::TYPE);

            foreach ((array) $package->getConfig(ScriptExecutor::TYPE) as $extender) {
                /** @var \Narrowspark\Automatic\Common\Contract\ScriptExtender $extender */
                if (isset($extenders[$extender::getType()])) {
                    throw new InvalidArgumentException(\sprintf('Script executor extender with the name [%s] already exists.', $extender::getType()));
                }

                if (! \is_subclass_of($extender, ScriptExtenderContract::class)) {
                    throw new InvalidArgumentException(\sprintf('The class [%s] must implement the interface [%s].', $extender, ScriptExtenderContract::class));
                }

                /** @var \Narrowspark\Automatic\Common\Contract\ScriptExtender $extender */
                $extenders[$extender::getType()] = $extender;
            }

            $lock->add(ScriptExecutor::TYPE, $extenders);
        }
    }

    /**
     * Run found skeleton generators.
     *
     * @throws \Exception
     *
     * @return void
     */
    private function runSkeletonGenerator(): void
    {
        /** @var \Narrowspark\Automatic\Lock $lock */
        $lock = $this->container->get(Lock::class);

        $lock->read();

        if ($lock->has(SkeletonInstaller::LOCK_KEY) && $this->container->get(IOInterface::class)->isInteractive()) {
            /** @var \Narrowspark\Automatic\SkeletonGenerator $skeletonGenerator */
            $skeletonGenerator = $this->container->get(SkeletonGenerator::class);

            $skeletonGenerator->run();

            $skeletonGenerator->remove();
        }

        $lock->clear();
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
            return 'You must enable the openssl extension in your "php.ini" file.';
        }
        // detect Composer >=1.7 (using the Composer::VERSION constant doesn't work with snapshot builds)
        if (! \class_exists(Comparer::class)) {
            return \sprintf('Your version "%s" of Composer is too old; Please upgrade.', Composer::VERSION);
        }
        // @codeCoverageIgnoreEnd

        // skip on no interactive mode
        if (! $io->isInteractive()) {
            return 'Composer running in a no interaction mode.';
        }

        return null;
    }
}
