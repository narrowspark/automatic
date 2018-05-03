<?php
declare(strict_types=1);
namespace Narrowspark\Discovery;

use Closure;
use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Downloader\FileDownloader;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Package\Locker;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\ProcessExecutor;
use FilesystemIterator;
use Hirak\Prestissimo\Plugin as PrestissimoPlugin;
use Narrowspark\Discovery\Common\Contract\Package as PackageContract;
use Narrowspark\Discovery\Common\Traits\ExpandTargetDirTrait;
use Narrowspark\Discovery\Installer\ConfiguratorInstaller;
use Narrowspark\Discovery\Installer\QuestionInstallationManager;
use Narrowspark\Discovery\Prefetcher\ParallelDownloader;
use Narrowspark\Discovery\Traits\GetGenericPropertyReaderTrait;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Discovery implements PluginInterface, EventSubscriberInterface
{
    use ExpandTargetDirTrait;
    use GetGenericPropertyReaderTrait;

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
     * @var \Narrowspark\Discovery\Lock
     */
    private $lock;

    /**
     * A configurator instance.
     *
     * @var \Narrowspark\Discovery\Configurator
     */
    private $configurator;

    /**
     * A extra dependency installation manager instance.
     *
     * @var \Narrowspark\Discovery\Installer\QuestionInstallationManager
     */
    private $extraInstaller;

    /**
     * A operations resolver instance.
     *
     * @var \Narrowspark\Discovery\OperationsResolver
     */
    private $operationsResolver;

    /**
     * A ParallelDownloader instance.
     *
     * @var \Narrowspark\Discovery\Prefetcher\ParallelDownloader
     */
    private $rfs;

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
     * @var bool
     */
    private $cacheDirPopulated = false;

    /**
     * @var array
     */
    private static $repoReadingCommands = [
        'create-project' => true,
        'outdated'       => true,
        'require'        => true,
        'update'         => true,
        'install'        => true,
    ];

    /**
     * @var \Composer\Config
     */
    private $composerConfig;

    /**
     * Return the composer json file and json manipulator.
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    public static function getComposerJsonFileAndManipulator(): array
    {
        $json        = new JsonFile(Factory::getComposerFile());
        $manipulator = new JsonManipulator(\file_get_contents($json->getPath()));

        return [$json, $manipulator];
    }

    /**
     * Get the discovery.lock file path.
     *
     * @return string
     */
    public static function getDiscoveryLockFile(): string
    {
        return \str_replace('composer', 'discovery', self::getComposerLockFile());
    }

    /**
     * Get the composer.lock file path.
     *
     * @return string
     */
    public static function getComposerLockFile(): string
    {
        return \mb_substr(Factory::getComposerFile(), 0, -4) . 'lock';
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'auto-scripts'                             => 'executeAutoScripts',
            InstallerEvents::PRE_DEPENDENCIES_SOLVING  => [['populateProvidersCacheDir', PHP_INT_MAX]],
            InstallerEvents::POST_DEPENDENCIES_SOLVING => [['populateFilesCacheDir', PHP_INT_MAX]],
            PackageEvents::PRE_PACKAGE_INSTALL         => [['populateFilesCacheDir', ~PHP_INT_MAX]],
            PackageEvents::PRE_PACKAGE_UPDATE          => [['populateFilesCacheDir', ~PHP_INT_MAX]],
            PackageEvents::POST_PACKAGE_INSTALL        => 'record',
            PackageEvents::POST_PACKAGE_UPDATE         => 'record',
            PackageEvents::POST_PACKAGE_UNINSTALL      => 'record',
            ScriptEvents::POST_INSTALL_CMD             => 'onPostInstall',
            ScriptEvents::POST_UPDATE_CMD              => 'onPostUpdate',
            ScriptEvents::POST_CREATE_PROJECT_CMD      => 'onPostCreateProject',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        // to avoid issues when Discovery is upgraded, we load all PHP classes now
        // that way, we are sure to use all files from the same version.
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)) as $file) {
            // @var \SplFileInfo $file
            if (\mb_substr($file->getFilename(), -4) === '.php') {
                require_once $file;
            }
        }

        $this->composer       = $composer;
        $this->composerConfig = $this->composer->getConfig();
        $this->io             = $io;
        $this->input          = $this->getGenericPropertyReader()($this->io, 'input');
        $this->projectOptions = $this->initProjectOptions();
        $this->lock           = new Lock(self::getDiscoveryLockFile());
        $this->vendorPath     = \rtrim($this->composerConfig->get('vendor-dir'), '/');

        $this->composer->getInstallationManager()->addInstaller(new ConfiguratorInstaller($this->io, $this->composer, $this->lock));

        $this->configurator       = new Configurator($this->composer, $this->io, $this->projectOptions);
        $this->operationsResolver = new OperationsResolver($this->lock, $this->vendorPath);
        $this->extraInstaller     = new QuestionInstallationManager($this->composer, $this->io, $this->input, $this->operationsResolver);

        $remoteFilesystem = Factory::createRemoteFilesystem($this->io, $this->composerConfig);
        $this->rfs        = new ParallelDownloader(
            $this->io,
            $this->composerConfig,
            $remoteFilesystem->getOptions(),
            $remoteFilesystem->isTlsDisabled()
        );

        $populateRepoCacheDir = __CLASS__ === self::class;

        if ($composer->getPluginManager()) {
            foreach ($composer->getPluginManager()->getPlugins() as $plugin) {
                if (\mb_strpos(\get_class($plugin), PrestissimoPlugin::class) === 0) {
                    if (\method_exists($remoteFilesystem, 'getRemoteContents')) {
                        $plugin->disable();
                    } else {
                        $this->cacheDirPopulated = true;
                    }

                    $populateRepoCacheDir = false;

                    break;
                }
            }
        }

//        try {
//            $command = $this->input->getFirstArgument();
//            $command = $command ? $app->find($command)->getName() : null;
//        } catch (\InvalidArgumentException $e) {
//        }
//
//        if ($populateRepoCacheDir
//            && isset(self::$repoReadingCommands[$command])
//            && ($command !== 'install' || (\file_exists(Factory::getComposerFile()) && ! \file_exists(self::getComposerLockFile())))
//        ) {
//            $this->populateRepoCacheDir();
//        }

        $this->lock->add('@readme', [
            'This file locks the discovery information of your project to a known state',
            'This file is @generated automatically',
        ]);
    }

    /**
     * Get the Configurator instance.
     *
     * @return \Narrowspark\Discovery\Configurator
     */
    public function getConfigurator(): Configurator
    {
        return $this->configurator;
    }

    /**
     * Get the Lock instance.
     *
     * @return \Narrowspark\Discovery\Lock
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

        $this->operations[] = $event->getOperation();
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
        [$json, $manipulator] = self::getComposerJsonFileAndManipulator();

        // new projects are most of the time proprietary
        $manipulator->addMainKey('license', 'proprietary');

        // 'name' and 'description' are only required for public packages
        $manipulator->removeProperty('name');
        $manipulator->removeProperty('description');

        foreach ($this->projectOptions as $key => $value) {
            if ($key !== 'discovery') {
                $manipulator->addSubNode('extra', $key, $value);
            }
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

        $discoveryOptions = $this->projectOptions['discovery'];
        $allowInstall     = $discoveryOptions['allow-auto-install'] ?? false;
        $packages         = $this->operationsResolver->resolve($this->operations);

        $this->io->writeError(\sprintf(
            '<info>Discovery operations: %s package%s</info>',
            \count($packages),
            \count($packages) > 1 ? 's' : ''
        ));

        foreach ((array) $this->lock->get(ConfiguratorInstaller::LOCK_KEY) as $path => $class) {
            require_once $this->vendorPath . $path;

            $this->configurator->add($class::getName(), $class);
        }

        foreach ($packages as $package) {
            if (isset($discoveryOptions['dont-discover']) && \array_key_exists($package->getName(), $discoveryOptions['dont-discover'])) {
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
                '<comment>The discovery.lock file has all information about the installed packages.</comment>',
                'Please <comment>review</comment>, <comment>edit</comment> and <comment>commit</comment> them: these files are <comment>yours</comment>.'
            );
        }

        $this->lock->write();

        if ($this->shouldUpdateComposerLock) {
            $this->updateComposerLock();
        }
    }

    /**
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

    public function populateProvidersCacheDir(InstallerEvent $event): void
    {
        $listed   = [];
        $packages = [];
        $pool     = $event->getPool();
//        $pool     = \Closure::bind(function () {
//            foreach ($this->providerRepos as $k => $repo) {
//                $this->providerRepos[$k] = new class($repo) extends BaseComposerRepository {
//                    private $repo;
//
//                    public function __construct($repo)
//                    {
//                        $this->repo = $repo;
//                    }
//
//                    public function whatProvides(Pool $pool, $name, $bypassFilters = false)
//                    {
//                        $packages = [];
//                        foreach ($this->repo->whatProvides($pool, $name, $bypassFilters) as $k => $p) {
//                            $packages[$k] = clone $p;
//                        }
//
//                        return $packages;
//                    }
//                };
//            }
//
//            return $this;
//        }, clone $pool, $pool)();

        foreach ($event->getRequest()->getJobs() as $job) {
            if ('install' !== $job['cmd'] || false === mb_strpos($job['packageName'], '/')) {
                continue;
            }

            $listed[$job['packageName']] = true;
            $packages[]                  = [$job['packageName'], $job['constraint']];
        }

        $this->rfs->download($packages, function ($packageName, $constraint) use (&$listed, &$packages, $pool): void {
            foreach ($pool->whatProvides($packageName, $constraint, true) as $package) {
                foreach (array_merge($package->getRequires(), $package->getConflicts(), $package->getReplaces()) as $link) {
                    if (isset($listed[$link->getTarget()]) || false === mb_strpos($link->getTarget(), '/')) {
                        continue;
                    }
                    $listed[$link->getTarget()] = true;
                    $packages[] = [$link->getTarget(), $link->getConstraint()];
                }
            }
        });
    }

    public function populateFilesCacheDir(InstallerEvent $event): void
    {
        $dryRun = false;

        if ($this->input->hasOption('dry-run')) {
            $dryRun = $this->input->getOption('dry-run');
        }

        if ($this->cacheDirPopulated || $dryRun) {
            return;
        }

        $this->cacheDirPopulated = true;

        $cacheDir  = \rtrim($this->composerConfig->get('cache-files-dir'), '\/');
        $downloads = [];

        foreach ($event->getOperations() as $operation) {
            // @var \Composer\Package\PackageInterface $package
            switch ($operation->getJobType()) {
                case 'install':
                    $package = $operation->getPackage();

                    break;
                case 'update':
                    $package = $operation->getTargetPackage();

                    break;
                default:
                    continue 2;
            }

            $url = $this->getUrlFromPackage($package);

            if ($url === null || ! $originUrl = \parse_url($url, PHP_URL_HOST)) {
                continue;
            }

            $destination = $cacheDir . DIRECTORY_SEPARATOR . $this->getCacheKey($package, $url);

            if (\file_exists($destination)) {
                continue;
            }

            @\mkdir(\dirname($destination), 0775, true);

            if (! \is_dir(\dirname($destination))) {
                continue;
            }

            if (\preg_match('#^https://github\.com/#', $package->getSourceUrl()) &&
                \preg_match('#^https://api\.github\.com/repos(/[^/]++/[^/]++/)zipball(.++)$#', $url, $matches)
            ) {
                $url = \sprintf('https://codeload.github.com%slegacy.zip%s', $matches[1], $matches[2]);
            }

            $downloads[] = [$originUrl, $url, [], $destination, false];
        }

        if (\count($downloads) < 1) {
            $progress = true;

            if ($this->input->hasOption('no-progress')) {
                $progress = ! $this->input->getOption('no-progress');
            }

            $this->rfs->download($downloads, [$this->rfs, 'get'], false, $progress);
        }
    }

    public function onFileDownload(PreFileDownloadEvent $event): void
    {
        if ($event->getRemoteFilesystem() !== $this->rfs) {
            $event->setRemoteFilesystem($this->rfs->setNextOptions($event->getRemoteFilesystem()->getOptions()));
        }
    }

    /**
     * Get the package url.
     *
     * @param \Composer\Package\PackageInterface $package
     *
     * @return null|string
     */
    private static function getUrlFromPackage(PackageInterface $package): ?string
    {
        $fileUrl = $package->getDistUrl();

        if (! $fileUrl) {
            return null;
        }

        if ($package->getDistMirrors()) {
            $fileUrl = \current($package->getDistUrls());
        }

        if (! \preg_match('/^https?:/', $fileUrl)) {
            return null;
        }

        return (string) $fileUrl;
    }

    /**
     * Get cache key from package and url.
     *
     * @param \Composer\Package\PackageInterface $package
     * @param string                             $url
     *
     * @return string
     */
    private function getCacheKey(PackageInterface $package, string $url): string
    {
        $fileDownloader = $this->composer->getDownloadManager()->getDownloader('file');

        $getCacheKey = Closure::bind(
            function (PackageInterface $package, $processedUrl) {
                return $this->getCacheKey($package, $processedUrl);
            },
            $fileDownloader,
            FileDownloader::class
        );

        return $getCacheKey($package, $url);
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
     * Update composer.lock file the composer.json do change.
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
            $composerJson
        );

        $lockData                  = $locker->getLockData();
        $lockData['_content-hash'] = Locker::getContentHash($composerJson);

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
        [$json, $manipulator] = self::getComposerJsonFileAndManipulator();

        $manipulator->addSubNode('extra', 'discovery.allow-auto-install', true);

        \file_put_contents($json->getPath(), $manipulator->getContents());
    }

    /**
     * Init default options.
     *
     * @return array
     */
    private function initProjectOptions(): array
    {
        return \array_merge(
            [
                'discovery' => [
                    'allow-auto-install' => false,
                    'dont-discover'      => [],
                ],
                'app-dir'       => 'app',
                'config-dir'    => 'config',
                'database-dir'  => 'database',
                'public-dir'    => 'public',
                'resources-dir' => 'resources',
                'routes-dir'    => 'routes',
                'tests-dir'     => 'tests',
                'storage-dir'   => 'storage',
            ],
            $this->composer->getPackage()->getExtra()
        );
    }

    /**
     * Choose action on package operation.
     *
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
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
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
     * @param \Narrowspark\Discovery\PackageConfigurator     $packageConfigurator
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
            $operations = $this->extraInstaller->install($package, $package->getConfiguratorOptions('extra-dependency'));
            $options    = \array_merge($options, ['selected-question-packages' => $this->extraInstaller->getPackagesToInstall()]);

            foreach ($operations as $operation) {
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
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
     * @param \Narrowspark\Discovery\PackageConfigurator     $packageConfigurator
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
}
