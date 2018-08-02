<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Prefetcher;

use Closure;
use Composer\Composer;
use Composer\Downloader\FileDownloader;
use Composer\Factory;
use Composer\Installer\InstallerEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginManager;
use Composer\Repository\ComposerRepository as BaseComposerRepository;
use Composer\Util\RemoteFilesystem;
use Hirak\Prestissimo\Plugin as PrestissimoPlugin;
use Narrowspark\Automatic\Automatic;
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
     * @var \Narrowspark\Automatic\Prefetcher\ParallelDownloader
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
     * @var bool
     */
    private $cacheDirPopulated = false;

    /**
     * Patch to the file cache.
     *
     * @var string
     */
    private $cacheFilesDir;

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
     * Create a new PreFetcher instance.
     *
     * @param \Composer\Composer                                   $composer
     * @param \Composer\IO\IOInterface                             $io
     * @param \Symfony\Component\Console\Input\InputInterface      $input
     * @param \Narrowspark\Automatic\Prefetcher\ParallelDownloader $rfs
     */
    public function __construct(Composer $composer, IOInterface $io, InputInterface $input, ParallelDownloader $rfs)
    {
        $this->composer       = $composer;
        $this->io             = $io;
        $this->input          = $input;
        $this->config         = $composer->getConfig();
        $this->fileDownloader = $composer->getDownloadManager()->getDownloader('file');
        $this->rfs            = $rfs;
        $this->cacheFilesDir  = \rtrim($this->config->get('cache-files-dir'), '\/');
    }

    /**
     * @param \Composer\Util\RemoteFilesystem $remoteFilesystem
     *
     * @return void
     */
    public function prefetchComposerRepositories(RemoteFilesystem $remoteFilesystem): void
    {
        $populateRepoCacheDir = __CLASS__ === self::class;
        $pluginManager        = $this->composer->getPluginManager();

        if ($pluginManager instanceof PluginManager) {
            foreach ($pluginManager->getPlugins() as $plugin) {
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

        $command = $this->input->getFirstArgument();

        if ($populateRepoCacheDir === true &&
            isset(self::$repoReadingCommands[$command]) &&
            ('install' !== $command || (\file_exists(Factory::getComposerFile()) && ! \file_exists(Automatic::getComposerLockFile())))
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

            $this->rfs->download($repos, function (BaseComposerRepository $repo): void {
                ParallelDownloader::$cacheNext = true;

                $repo->getProviderNames();
            });
        }
    }

    /**
     * @param \Composer\Installer\InstallerEvent $event
     *
     * @return void
     */
    public function fetchAllFromOperations(InstallerEvent $event): void
    {
        if ($this->cacheDirPopulated === true || $this->getDryRun() === true) {
            return;
        }

        $this->cacheDirPopulated = true;

        $downloads = [];

        foreach ($event->getOperations() as $i => $operation) {
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

            if ($url === null || ! $originUrl = \parse_url($url, \PHP_URL_HOST)) {
                continue;
            }

            $destination = $this->cacheFilesDir . \DIRECTORY_SEPARATOR . $this->getCacheKey($package, $url);

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

        if (\count($downloads) > 1) {
            $progress = true;

            if ($this->input->hasOption('no-progress')) {
                $progress = ! $this->input->getOption('no-progress');
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

        if ($this->input->hasOption('dry-run')) {
            $dryRun = $this->input->getOption('dry-run');
        }

        return $dryRun;
    }
}
