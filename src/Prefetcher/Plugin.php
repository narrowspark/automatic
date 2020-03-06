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

namespace Narrowspark\Automatic\Prefetcher;

use Closure;
use Composer\Composer;
use Composer\Config;
use Composer\Console\Application;
use Composer\DependencyResolver\Pool;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\BasePackage;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Repository\ComposerRepository as BaseComposerRepository;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use FilesystemIterator;
use InvalidArgumentException;
use Narrowspark\Automatic\Common\AbstractContainer;
use Narrowspark\Automatic\Common\Downloader\ParallelDownloader;
use Narrowspark\Automatic\Prefetcher\Common\Util;
use Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager as LegacyTagsManagerContract;
use Narrowspark\Automatic\Prefetcher\Contract\Prefetcher as PrefetcherContract;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use SplFileInfo;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;

class Plugin implements EventSubscriberInterface, PluginInterface
{
    /** @var string */
    public const VERSION = '0.13.1';

    /** @var string */
    public const COMPOSER_EXTRA_KEY = 'prefetcher';

    /** @var string */
    public const PACKAGE_NAME = 'narrowspark/automatic-composer-prefetcher';

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
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        if (($errorMessage = $this->getErrorMessage()) !== null) {
            self::$activated = false;

            $io->writeError('<warning>Narrowspark Automatic Prefetcher has been disabled. ' . $errorMessage . '</warning>');

            return;
        }

        if (! \class_exists(AbstractContainer::class)) {
            require __DIR__ . \DIRECTORY_SEPARATOR . 'alias.php';
        }

        // to avoid issues when Automatic Prefetcher is upgraded, we load all PHP classes now
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

            $io->writeError('<warning>Narrowspark Automatic Prefetcher has been disabled. No input object found on composer class.</warning>');

            return;
        }

        /** @var \Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager $tagsManager */
        $tagsManager = $this->container->get(LegacyTagsManagerContract::class);

        $this->configureLegacyTagsManager($io, $tagsManager, $this->container->get('composer-extra'));

        $composer->setRepositoryManager($this->extendRepositoryManager($composer, $io, $tagsManager));

        // overwrite composer instance
        $this->container->set(Composer::class, static function () use ($composer): Composer {
            return $composer;
        });

        $this->extendComposer(\debug_backtrace(), $tagsManager);
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
            InstallerEvents::PRE_DEPENDENCIES_SOLVING => [['onPreDependenciesSolving', \PHP_INT_MAX]],
            InstallerEvents::POST_DEPENDENCIES_SOLVING => [['populateFilesCacheDir', \PHP_INT_MAX]],
            PackageEvents::PRE_PACKAGE_INSTALL => [['populateFilesCacheDir', ~\PHP_INT_MAX]],
            PackageEvents::PRE_PACKAGE_UPDATE => [['populateFilesCacheDir', ~\PHP_INT_MAX]],
            PluginEvents::PRE_FILE_DOWNLOAD => 'onFileDownload',
        ];
    }

    /**
     * Populate the provider cache.
     */
    public function onPreDependenciesSolving(InstallerEvent $event): void
    {
        $listed = [];
        $packages = [];
        $pool = $event->getPool();
        $pool = Closure::bind(function () {
            foreach ($this->providerRepos as $k => $repo) {
                $this->providerRepos[$k] = new class($repo) extends BaseComposerRepository {
                    /**
                     * A repository implementation.
                     *
                     * @var \Composer\Repository\RepositoryInterface
                     */
                    private $repo;

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
            if ($job['cmd'] !== 'install' || \strpos($job['packageName'], '/') === false) {
                continue;
            }

            $listed[$job['packageName']] = true;
            $packages[] = [$job['packageName'], $job['constraint']];
        }

        $loadExtraRepos = ! (new ReflectionMethod(Pool::class, 'match'))->isPublic(); // Detect Composer < 1.7.3

        $this->container->get(ParallelDownloader::class)->download($packages, static function (string $packageName, $constraint) use (&$listed, &$packages, $pool, $loadExtraRepos): void {
            /** @var \Composer\Package\PackageInterface $package */
            foreach ($pool->whatProvides($packageName, $constraint, true) as $package) {
                $links = $loadExtraRepos ? \array_merge($package->getRequires(), $package->getConflicts(), $package->getReplaces()) : $package->getRequires();

                /** @var \Composer\Package\Link $link */
                foreach ($links as $link) {
                    if (isset($listed[$link->getTarget()]) || \strpos($link->getTarget(), '/') === false) {
                        continue;
                    }

                    $listed[$link->getTarget()] = true;
                    $packages[] = [$link->getTarget(), $link->getConstraint()];
                }
            }
        });
    }

    /**
     * Wrapper for the fetchAllFromOperations function.
     *
     * @see \Narrowspark\Automatic\Prefetcher\Contract\Prefetcher::fetchAllFromOperations()
     *
     * @param \Composer\Installer\InstallerEvent|\Composer\Installer\PackageEvent $event
     */
    public function populateFilesCacheDir($event): void
    {
        /** @var \Narrowspark\Automatic\Prefetcher\Contract\Prefetcher $prefetcher */
        $prefetcher = $this->container->get(PrefetcherContract::class);

        $prefetcher->fetchAllFromOperations($event);
    }

    /**
     * Adds the parallel downloader to composer.
     */
    public function onFileDownload(PreFileDownloadEvent $event): void
    {
        /** @var \Narrowspark\Automatic\Common\Downloader\ParallelDownloader $rfs */
        $rfs = $this->container->get(ParallelDownloader::class);

        if ($event->getRemoteFilesystem() !== $rfs) {
            $event->setRemoteFilesystem($rfs->setNextOptions($event->getRemoteFilesystem()->getOptions()));
        }
    }

    /**
     * Configure the LegacyTagsManager with legacy package requires.
     */
    private function configureLegacyTagsManager(
        IOInterface $io,
        LegacyTagsManagerContract $tagsManager,
        array $extra
    ): void {
        if (false !== $envRequire = \getenv('AUTOMATIC_PREFETCHER_REQUIRE')) {
            $requires = [];

            foreach (\explode(',', $envRequire) as $packageString) {
                [$packageName, $version] = \explode(':', $packageString, 2);

                $requires[$packageName] = $version;
            }

            $this->addLegacyTags($io, $requires, $tagsManager);
        } elseif (isset($extra[static::COMPOSER_EXTRA_KEY]['require'])) {
            $this->addLegacyTags($io, $extra[static::COMPOSER_EXTRA_KEY]['require'], $tagsManager);
        }
    }

    /**
     * Add found legacy tags to the tags manager.
     */
    private function addLegacyTags(IOInterface $io, array $requires, LegacyTagsManagerContract $tagsManager): void
    {
        foreach ($requires as $name => $version) {
            if (\is_int($name)) {
                $io->writeError(\sprintf('Constrain [%s] skipped, because package name is a number [%s]', $version, $name));

                continue;
            }

            if (\strpos($name, '/') === false) {
                $io->writeError(\sprintf('Constrain [%s] skipped, package name [%s] without a slash is not supported', $version, $name));

                continue;
            }

            $tagsManager->addConstraint($name, $version);
        }
    }

    /**
     * Extend the repository manager with a truncated composer repository and parallel downloader.
     */
    private function extendRepositoryManager(
        Composer $composer,
        IOInterface $io,
        LegacyTagsManagerContract $tagsManager
    ): RepositoryManager {
        $manager = RepositoryFactory::manager(
            $io,
            $this->container->get(Config::class),
            $this->container->get(Composer::class)->getEventDispatcher(),
            $this->container->get(ParallelDownloader::class)
        );

        $setRepositories = Closure::bind(function (RepositoryManager $manager) use ($tagsManager): void {
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
     * Check if automatic can be activated.
     */
    private function getErrorMessage(): ?string
    {
        // @codeCoverageIgnoreStart
        if (! extension_loaded('openssl')) {
            return 'You must enable the openssl extension in your [php.ini] file';
        }

        if (\version_compare(Util::getComposerVersion(), '1.8.0', '<')) {
            return \sprintf('Your version "%s" of Composer is too old; Please upgrade', Composer::VERSION);
        }
        // @codeCoverageIgnoreEnd

        return null;
    }

    /**
     * Extend the composer object with some automatic prefetcher settings.
     *
     * @param array<int|string, mixed> $backtrace
     */
    private function extendComposer(array $backtrace, LegacyTagsManagerContract $tagsManager): void
    {
        foreach ($backtrace as $trace) {
            if (! isset($trace['object']) || ! isset($trace['args'][0])) {
                continue;
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

            if ($command === 'outdated') {
                $tagsManager->reset();
            }

            // When prefer-lowest is set and no stable version has been released,
            // we consider "dev" more stable than "alpha", "beta" or "RC". This
            // allows testing lowest versions with potential fixes applied.
            if ($input->hasParameterOption('--prefer-lowest', true)) {
                BasePackage::$stabilities['dev'] = 1 + BasePackage::STABILITY_STABLE;
            }

            $this->container->get(PrefetcherContract::class)->prefetchComposerRepositories();

            break;
        }

        $this->container->get(PrefetcherContract::class)->populateRepoCacheDir();
    }
}
