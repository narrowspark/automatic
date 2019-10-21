<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Prefetcher;

use Closure;
use Composer\Composer;
use Composer\Downloader\FileDownloader;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginManager;
use Composer\Repository\ComposerRepository as BaseComposerRepository;
use Hirak\Prestissimo\Plugin as PrestissimoPlugin;
use Narrowspark\Automatic\Common\Util;
use Narrowspark\Automatic\Prefetcher\Downloader\ParallelDownloader;
use Symfony\Component\Console\Input\InputInterface;

class Prefetcher
{
    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * A input implementation.
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    /**
     * A composer instance.
     *
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * A ParallelDownloader instance.
     *
     * @var \Narrowspark\Automatic\Prefetcher\Downloader\ParallelDownloader
     */
    private $rfs;

    /**
     * A downloader implementation.
     *
     * @var \Composer\Downloader\DownloaderInterface
     */
    private $fileDownloader;

    /**
     * A Composer Config instance.
     *
     * @var \Composer\Config
     */
    private $config;

    /**
     * Patch to the file cache.
     *
     * @var string
     */
    private $cacheFilesDir;

    /**
     * Check if the repo cache should be populated.
     *
     * @var null|bool
     */
    private $populateRepoCacheDir;

    /**
     * Check if the dir cache should be populated.
     *
     * @var bool
     */
    private $cacheDirPopulated = false;

    /** @var array */
    private static $repoReadingCommands = [
        'create-project' => true,
        'outdated' => true,
        'require' => true,
        'update' => true,
        'install' => true,
    ];

    /**
     * Create a new PreFetcher instance.
     *
     * @param \Composer\Composer                                              $composer
     * @param \Composer\IO\IOInterface                                        $io
     * @param \Symfony\Component\Console\Input\InputInterface                 $input
     * @param \Narrowspark\Automatic\Prefetcher\Downloader\ParallelDownloader $rfs
     */
    public function __construct(Composer $composer, IOInterface $io, InputInterface $input, ParallelDownloader $rfs)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->input = $input;
        $this->config = $composer->getConfig();
        $this->fileDownloader = $composer->getDownloadManager()->getDownloader('file');
        $this->rfs = $rfs;
        $this->cacheFilesDir = \rtrim($this->config->get('cache-files-dir'), '\/');
    }

    /**
     * Should the repo- and dir cache be populated.
     *
     * @return void
     */
    public function populateRepoCacheDir(): void
    {
        $this->populateRepoCacheDir = __CLASS__ === self::class;
        $pluginManager = $this->composer->getPluginManager();

        if ($pluginManager instanceof PluginManager) {
            foreach ($pluginManager->getPlugins() as $plugin) {
                if (\strpos(\get_class($plugin), PrestissimoPlugin::class) === 0) {
                    if (\method_exists($this->rfs, 'getRemoteContents')) {
                        $plugin->disable();
                    } else {
                        $this->cacheDirPopulated = true;
                    }

                    $this->populateRepoCacheDir = false;

                    break;
                }
            }
        }
    }

    /**
     * @return void
     */
    public function prefetchComposerRepositories(): void
    {
        $command = $this->input->getFirstArgument();

        if ($this->populateRepoCacheDir === true
            && isset(self::$repoReadingCommands[$command])
            && ($command !== 'install' || (\file_exists(Factory::getComposerFile()) && ! \file_exists(Util::getComposerLockFile())))
        ) {
            $repos = [];

            foreach ($this->composer->getPackage()->getRepositories() as $name => $repo) {
                if (! isset($repo['type']) || $repo['type'] !== 'composer' || ! empty($repo['force-lazy-providers'])) {
                    continue;
                }

                /** @see https://github.com/composer/composer/blob/master/src/Composer/Repository/ComposerRepository.php#L74 */
                if (! \preg_match('#^http(s\??)?://#', $repo['url'])) {
                    continue;
                }

                $repos[] = [new ComposerRepository($repo, $this->io, $this->config, null, $this->rfs)];
            }

            $this->rfs->download($repos, static function (BaseComposerRepository $repo): void {
                ParallelDownloader::$cacheNext = true;

                $repo->getProviderNames();
            });
        }
    }

    /**
     * @param \Composer\Installer\InstallerEvent|\Composer\Installer\PackageEvent $event
     *
     * @return void
     */
    public function fetchAllFromOperations($event): void
    {
        if ($this->cacheDirPopulated === true || $this->getDryRun() === true) {
            return;
        }

        $this->cacheDirPopulated = true;

        $downloads = [];

        foreach ($event->getOperations() as $i => $operation) {
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

            $url = self::getUrlFromPackage($package);

            if ($url === null || ! $originUrl = \parse_url($url, \PHP_URL_HOST)) {
                continue;
            }

            $destination = $this->cacheFilesDir . \DIRECTORY_SEPARATOR . $this->getCacheKey($package, $url);

            if (\file_exists($destination)) {
                continue;
            }

            if (! @\mkdir($concurrentDirectory = \dirname($destination), 0775, true) && ! \is_dir($concurrentDirectory)) {
                continue;
            }

            if (\preg_match('#^https://github\.com/#', $package->getSourceUrl())
                && \preg_match('#^https://api\.github\.com/repos(/[^/]++/[^/]++/)zipball(.++)$#', $url, $matches)
            ) {
                $url = \sprintf('https://codeload.github.com%slegacy.zip%s', $matches[1], $matches[2]);
            }

            $downloads[] = [$originUrl, $url, [], $destination, false];
        }

        if (\count($downloads) > 1) {
            $progress = true;

            if ($this->input->hasParameterOption('--no-progress', true)) {
                $progress = false;
            }

            $this->rfs->download($downloads, [$this->rfs, 'get'], false, $progress);
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
        $getCacheKey = Closure::bind(
            function (PackageInterface $package, $processedUrl) {
                return $this->getCacheKey($package, $processedUrl);
            },
            $this->fileDownloader,
            FileDownloader::class
        );

        return $getCacheKey($package, $url);
    }

    /**
     * Check if composer is in dry-run mode.
     *
     * @return bool
     */
    private function getDryRun(): bool
    {
        $dryRun = false;

        if ($this->input->hasParameterOption('--dry-run')) {
            $dryRun = true;
        }

        return $dryRun;
    }
}
