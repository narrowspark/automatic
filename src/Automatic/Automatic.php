<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Closure;
use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Pool;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\Locker;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Repository\ComposerRepository as BaseComposerRepository;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\ProcessExecutor;
use FilesystemIterator;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Traits\ExpandTargetDirTrait;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Common\Util;
use Narrowspark\Automatic\Installer\ConfiguratorInstaller;
use Narrowspark\Automatic\Installer\QuestionInstallationManager;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\Prefetcher\ParallelDownloader;
use Narrowspark\Automatic\Prefetcher\Prefetcher;
use Narrowspark\Automatic\Prefetcher\TruncatedComposerRepository;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

class Automatic implements PluginInterface, EventSubscriberInterface
{
    use ExpandTargetDirTrait;
    use GetGenericPropertyReaderTrait;

    /**
     * Check if the the plugin is activated.
     *
     * @var bool
     */
    private static $activated = true;

    /**
     * A composer instance.
     *
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * A lock instance.
     *
     * @var \Narrowspark\Automatic\Lock
     */
    private $lock;

    /**
     * A configurator instance.
     *
     * @var \Narrowspark\Automatic\Configurator
     */
    private $configurator;

    /**
     * A extra dependency installation manager instance.
     *
     * @var \Narrowspark\Automatic\Installer\QuestionInstallationManager
     */
    private $extraInstaller;

    /**
     * A operations resolver instance.
     *
     * @var \Narrowspark\Automatic\OperationsResolver
     */
    private $operationsResolver;

    /**
     * A ParallelDownloader instance.
     *
     * @var \Narrowspark\Automatic\Prefetcher\ParallelDownloader
     */
    private $rfs;

    /**
     * A PreFetcher instance.
     *
     * @var \Narrowspark\Automatic\Prefetcher\Prefetcher
     */
    private $prefetcher;

    /**
     * A input implementation.
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    /**
     * The composer vendor path.
     *
     * @var string
     */
    private $vendorPath;

    /**
     * A array of project options.
     *
     * @var array
     */
    private $projectOptions;

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
        if (($errorMessage = $this->getErrorMessage()) !== null) {
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

        $this->composer = $composer;
        $this->io       = $io;
        $this->input    = $this->getGenericPropertyReader()($this->io, 'input');
        $this->lock     = new Lock(Util::getAutomaticLockFile());

        $this->projectOptions = \array_merge(
            [
                Util::AUTOMATIC => [
                    'allow-auto-install' => false,
                    'dont-discover'      => [],
                ],
            ],
            $this->composer->getPackage()->getExtra()
        );

        $composerConfig   = $this->composer->getConfig();
        $this->vendorPath = \rtrim($composerConfig->get('vendor-dir'), '/');

        $pathLoader           = new PathClassLoader();
        $installationManager  = $this->composer->getInstallationManager();

        $installationManager->addInstaller(new ConfiguratorInstaller($this->io, $this->composer, $this->lock, $pathLoader));
        $installationManager->addInstaller(new SkeletonInstaller($this->io, $this->composer, $this->lock, $pathLoader));

        $this->configurator       = new Configurator($this->composer, $this->io, $this->projectOptions);
        $this->operationsResolver = new OperationsResolver($this->lock, $this->vendorPath);
        $this->extraInstaller     = new QuestionInstallationManager($this->composer, $this->io, $this->input, $this->operationsResolver);

        $rfs       = Factory::createRemoteFilesystem($this->io, $composerConfig);
        $this->rfs = new ParallelDownloader($this->io, $composerConfig, $rfs->getOptions(), $rfs->isTlsDisabled());

        $this->prefetcher = new Prefetcher($this->composer, $this->io, $this->input, $this->rfs);
        $this->prefetcher->prefetchComposerRepositories($rfs);

        $manager         = RepositoryFactory::manager($this->io, $composerConfig, $composer->getEventDispatcher(), $this->rfs);
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

        $this->lock->add('@readme', [
            'This file locks the automatic information of your project to a known state',
            'This file is @generated automatically',
        ]);
    }

    /**
     * Get the Configurator instance.
     *
     * @return \Narrowspark\Automatic\Configurator
     */
    public function getConfigurator(): Configurator
    {
        return $this->configurator;
    }

    /**
     * Get the Lock instance.
     *
     * @return \Narrowspark\Automatic\Lock
     */
    public function getLock(): Lock
    {
        return $this->lock;
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

        if ($operation instanceof InstallOperation && $operation->getPackage()->getName() === 'narrowspark/automatic') {
            \array_unshift($this->operations, $operation);
        } else {
            $this->operations[] = $operation;
        }
    }

    /**
     * Execute on composer command event.
     *
     * @param \Composer\Plugin\CommandEvent $event
     *
     * @return void
     */
    public function onCommand(CommandEvent $event): void
    {
        if ($event->getInput()->hasOption('no-suggest')) {
            $event->getInput()->setOption('no-suggest', true);
        }

        if ($event->getInput()->hasOption('remove-vcs')) {
            $event->getInput()->setOption('remove-vcs', true);
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

        foreach ($this->projectOptions as $key => $value) {
            if ($key !== Util::AUTOMATIC) {
                $manipulator->addSubNode('extra', $key, $value);
            }
        }

        $this->lock->read();

        if ($this->lock->has(SkeletonInstaller::LOCK_KEY)) {
            foreach (Util::flattenArray(\array_values((array) $this->lock->get(SkeletonInstaller::LOCK_KEY_CLASSMAP))) as $path) {
                require_once \str_replace('%vendor_path%', $this->vendorPath, $path);
            }

            $skeletonGenerator = new SkeletonGenerator(
                $this->projectOptions,
                (array) $this->lock->get(SkeletonInstaller::LOCK_KEY),
                $this->io
            );

            $skeletonGenerator->run();
            $skeletonGenerator->remove($manipulator, $this->lock);

            $this->lock->clear();
        }

        \file_put_contents($json->getPath(), $manipulator->getContents());

        $this->updateComposerLock();
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

        $automaticOptions = $this->projectOptions[Util::AUTOMATIC];
        $allowInstall     = $automaticOptions['allow-auto-install'] ?? false;
        $packages         = $this->operationsResolver->resolve($this->operations);

        $this->io->writeError(\sprintf(
            '<info>Automatic operations: %s package%s</info>',
            \count($packages),
            \count($packages) > 1 ? 's' : ''
        ));

        foreach (Util::flattenArray(\array_values((array) $this->lock->get(ConfiguratorInstaller::LOCK_KEY_CLASSMAP))) as $path) {
            require_once \str_replace('%vendor_path%', $this->vendorPath, $path);
        }

        foreach (Util::flattenArray(\array_values((array) $this->lock->get(ConfiguratorInstaller::LOCK_KEY))) as $class) {
            $reflectionClass = new ReflectionClass($class);

            if ($reflectionClass->isInstantiable() && $reflectionClass->hasMethod('getName')) {
                $this->configurator->add($class::getName(), $reflectionClass->getName());
            }
        }

        foreach ($packages as $package) {
            if (isset($automaticOptions['dont-discover']) && \array_key_exists($package->getName(), $automaticOptions['dont-discover'])) {
                $this->io->write(\sprintf('<info>Package "%s" was ignored.</info>', $package->getName()));

                return;
            }

            if ($allowInstall === false && $package->getOperation() === 'install') {
                $answer = $this->io->askAndValidate(
                    QuestionFactory::getPackageQuestion($package->getUrl()),
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
                'Please <comment>review</comment>, <comment>edit</comment> and <comment>commit</comment> them: these files are <comment>yours</comment>.'
            );
        }

        $this->lock->write();

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
        $executor     = new ScriptExecutor($this->composer, $this->io, $this->projectOptions, new ProcessExecutor());

        foreach ($jsonContents['scripts']['auto-scripts'] as $cmd => $type) {
            $executor->execute($type, $cmd);
        }

        $this->io->write($this->postInstallOutput);
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

        $this->rfs->download($packages, function ($packageName, $constraint) use (&$listed, &$packages, $pool): void {
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
        $this->prefetcher->fetchAllFromOperations($event);
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
        if ($event->getRemoteFilesystem() !== $this->rfs) {
            $event->setRemoteFilesystem($this->rfs->setNextOptions($event->getRemoteFilesystem()->getOptions()));
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
        if (! $event->isDevMode() && $operation instanceof UninstallOperation) {
            foreach ($event->getComposer()->getLocker()->getLockData()['packages-dev'] as $devPackage) {
                if ($package->getName() === $devPackage['name']) {
                    return false;
                }
            }
        }

        return ($operation instanceof InstallOperation && ! $this->lock->has($package->getName())) || $operation instanceof UninstallOperation;
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

    /**
     * Add extra option "allow-auto-install" to composer.json.
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    private function manipulateComposerJsonWithAllowAutoInstall(): void
    {
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
        $packageConfigurator = new PackageConfigurator(
            $this->composer,
            $this->io,
            $this->projectOptions,
            $package->getConfiguratorOptions('custom-configurators')
        );

        if ($package->getOperation() === 'install') {
            $this->doInstall($package, $packageConfigurator);
        } elseif ($package->getOperation() === 'uninstall') {
            $this->doUninstall($package, $packageConfigurator);
        }
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
        $this->io->writeError(\sprintf('  - Configuring %s', $package->getName()));

        $this->configurator->configure($package);
        $packageConfigurator->configure($package);

        $options = $package->getOptions();

        if ($package->hasConfiguratorKey('extra-dependency')) {
            $extraDependency = $package->getConfiguratorOptions('extra-dependency');
            $options         = \array_merge(
                $options,
                ['selected-question-packages' => $this->extraInstaller->getPackagesToInstall()]
            );

            foreach ($this->extraInstaller->install($package, $extraDependency) as $operation) {
                $this->doInstall($operation, $packageConfigurator);
            }
        }

        if ($package->hasConfiguratorKey('post-install-output')) {
            foreach ($package->getConfiguratorOptions('post-install-output') as $line) {
                $this->postInstallOutput[] = self::expandTargetDir($this->projectOptions, $line);
            }

            $this->postInstallOutput[] = '';
        }

        $this->lock->add($package->getName(), $options);
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
        $this->io->writeError(\sprintf('  - Unconfiguring %s', $package->getName()));

        $this->configurator->unconfigure($package);
        $packageConfigurator->unconfigure($package);

        if ($package->hasConfiguratorKey('extra-dependency')) {
            $extraDependencies = [];

            foreach ($this->lock->read() as $packageName => $data) {
                if (isset($data['extra-dependency-of']) && $data['extra-dependency-of'] === $package->getName()) {
                    $extraDependencies[$packageName] = $data['version'];

                    foreach ((array) $data['require'] as $name => $version) {
                        $extraDependencies[$name] = $version;
                    }
                }
            }

            foreach ($this->extraInstaller->uninstall($package, $extraDependencies) as $operation) {
                $this->doUninstall($operation, $packageConfigurator);
            }
        }

        $this->lock->remove($package->getName());
    }

    /**
     * @codeCoverageIgnore
     *
     * Check if automatic can be activated.
     *
     * @return null|string
     */
    private function getErrorMessage(): ?string
    {
        if (! \extension_loaded('openssl')) {
            return 'You must enable the openssl extension in your "php.ini" file.';
        }

        \preg_match('/\d+.\d+.\d+/m', Composer::VERSION, $matches);

        if ($matches !== null && \version_compare($matches[0], '1.6.0') === -1) {
            return \sprintf('Your version "%s" of Composer is too old; Please upgrade.', Composer::VERSION);
        }

        return null;
    }
}
