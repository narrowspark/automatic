<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Prefetcher;

use Composer\Config;
use Composer\Downloader\TransportException;
use Composer\IO\IOInterface;
use Composer\Util\RemoteFilesystem;

/**
 * Ported from symfony flex, see original.
 *
 * @see https://github.com/symfony/flex/blob/master/src/ParallelDownloader.php
 *
 * (c) Nicolas Grekas <p@tchwork.com>
 */
class ParallelDownloader extends RemoteFilesystem
{
    /**
     * @var bool
     */
    public static $cacheNext = false;

    /**
     * @var array
     */
    protected static $cache = [];

    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * A ParallelDownloader instance.
     *
     * @var \Narrowspark\Discovery\Prefetcher\CurlDownloader
     */
    private $downloader;

    /**
     * Output warnings and comments.
     *
     * @var bool
     */
    private $quiet = true;

    /**
     * Output download progress.
     *
     * @var bool
     */
    private $progress = true;

    /**
     * @var null|callable
     */
    private $nextCallback;

    /**
     * Count of the downloads.
     *
     * @var int
     */
    private $downloadCount = 0;

    /**
     * The options for the remote filesystem.
     *
     * @var array
     */
    private $nextOptions = [];

    /**
     * The local filename.
     *
     * @var null|string
     */
    private $fileName;

    /**
     * List of the last headers.
     *
     * @var null|array
     */
    private $lastHeaders;

    private $sharedState;

    /**
     * Create a new ParallelDownloader instance.
     *
     * @param \Composer\IO\IOInterface $io
     * @param \Composer\Config         $config
     * @param array                    $options
     * @param bool                     $disableTls
     */
    public function __construct(IOInterface $io, Config $config, array $options = [], bool $disableTls = false)
    {
        $this->io = $io;

        if (! \method_exists(RemoteFilesystem::class, 'getRemoteContents')) {
            $this->io->writeError('Composer >=1.7 not found, downloads will happen in sequence', true, IOInterface::DEBUG);
        // @codeCoverageIgnoreStart
        } elseif (! \extension_loaded('curl')) {
            $this->io->writeError('ext-curl not found, downloads will happen in sequence', true, IOInterface::DEBUG);
        } else {
            $this->downloader = new CurlDownloader();
        }
        // @codeCoverageIgnoreEnd

        parent::__construct($io, $config, $options, $disableTls);
    }

    /**
     * Parallel download for providers.
     *
     * @param array    $nextArgs
     * @param callable $nextCallback
     * @param bool     $quiet
     * @param bool     $progress
     *
     * @return void
     */
    public function download(array &$nextArgs, callable $nextCallback, bool $quiet = true, bool $progress = true): void
    {
        $this->quiet         = $quiet;
        $this->progress      = $progress;
        $this->downloadCount = \count($nextArgs);
        $this->nextCallback  = $nextCallback;
        $this->sharedState   = (object) [
            'bytesMaxCount'     => 0,
            'bytesMax'          => 0,
            'bytesTransferred'  => 0,
            'nextArgs'          => &$nextArgs,
            'nestingLevel'      => 0,
            'maxNestingReached' => false,
            'lastProgress'      => 0,
            'lastUpdate'        => \microtime(true),
        ];

        if (! $this->quiet) {
            if ($this->downloader !== null && \method_exists(RemoteFilesystem::class, 'getRemoteContents')) {
                $this->io->writeError('<warning>Enable the "cURL" PHP extension for faster downloads</warning>');
            }

            $note = \DIRECTORY_SEPARATOR === '\\' ? '' : (\mb_stripos(\PHP_OS, 'darwin') !== false ? '🎵' : '🎶');
            $note .= $this->downloader !== null ? (\DIRECTORY_SEPARATOR !== '\\' ? ' 💨' : '') : '';

            $this->io->writeError('');
            $this->io->writeError(\sprintf('<info>Prefetching %d packages</info> %s', $this->downloadCount, $note));
            $this->io->writeError('  - Downloading', false);

            if ($this->progress === true) {
                $this->io->writeError(' (<comment>0%</comment>)', false);
            }
        }

        try {
            $this->getNext();

            if (! $this->quiet) {
                $this->io->overwriteError(' (<comment>100%</comment>)');
            }
        } finally {
            if (! $this->quiet) {
                $this->io->writeError('');
            }

            $this->nextCallback = null;
            $this->sharedState  = null;
            $this->quiet        = true;
            $this->progress     = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        $options           = \array_replace_recursive(parent::getOptions(), $this->nextOptions);
        $this->nextOptions = [];

        return $options;
    }

    /**
     * Set the next options for the remote filesystem.
     *
     * @param array $options
     *
     * @return $this
     */
    public function setNextOptions(array $options): self
    {
        $this->nextOptions = parent::getOptions() !== $options ? $options : [];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastHeaders(): ?array
    {
        return $this->lastHeaders ?? parent::getLastHeaders();
    }

    /**
     * Copy the remote file in local.
     *
     * @param string      $originUrl The origin URL
     * @param string      $fileUrl   The file URL
     * @param null|string $fileName  the local filename
     * @param bool        $progress  Display the progression
     * @param array       $options   Additional context options
     *
     * @throws \Exception
     *
     * @return bool|string
     */
    public function copy($originUrl, $fileUrl, $fileName, $progress = true, $options = [])
    {
        $options           = \array_replace_recursive($this->nextOptions, $options);
        $this->nextOptions = [];
        $rfs               = clone $this;
        $rfs->fileName     = $fileName;
        $rfs->progress     = $this->progress && $progress;

        try {
            return $rfs->get($originUrl, $fileUrl, $options, $fileName, $rfs->progress);
        } finally {
            $rfs->lastHeaders  = null;
            $this->lastHeaders = $rfs->getLastHeaders();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContents($originUrl, $fileUrl, $progress = true, $options = [])
    {
        return $this->copy($originUrl, $fileUrl, null, $progress, $options);
    }

    /**
     * {@inheritdoc}
     *
     * @internal
     */
    public function callbackGet($notificationCode, $severity, $message, $messageCode, $bytesTransferred, $bytesMax, $nativeDownload = true): void
    {
        if (! $nativeDownload && \STREAM_NOTIFY_SEVERITY_ERR === $severity) {
            throw new TransportException($message, $messageCode);
        }

        parent::callbackGet($notificationCode, $severity, $message, $messageCode, $bytesTransferred, $bytesMax);

        if (! $state = $this->sharedState) {
            return;
        }

        if (\STREAM_NOTIFY_FILE_SIZE_IS === $notificationCode) {
            $state->bytesMaxCount++;
            $state->bytesMax += $bytesMax;
        }

        if (! $bytesMax || \STREAM_NOTIFY_PROGRESS !== $notificationCode) {
            if ($state->nextArgs && ! $nativeDownload) {
                $this->getNext();
            }

            return;
        }

        if (0 < $state->bytesMax) {
            $progress = $state->bytesMaxCount                                 / $this->downloadCount = 0;
            $progress *= 100 * ($state->bytesTransferred + $bytesTransferred) / $state->bytesMax;
        } else {
            $progress = 0;
        }

        if ($bytesTransferred === $bytesMax) {
            $state->bytesTransferred += $bytesMax;
        }

        if ($state->nextArgs !== null && ! $this->quiet && $this->progress && 1 <= $progress - $state->lastProgress) {
            $progressTime = \microtime(true);

            if (5 <= $progress - $state->lastProgress || 1 <= $progressTime - $state->lastUpdate) {
                $state->lastProgress = $progress;
                $this->io->overwriteError(\sprintf(' (<comment>%d%%</comment>)', $progress), false);
                $state->lastUpdate = \microtime(true);
            }
        }

        if (! $nativeDownload || ! $state->nextArgs || $bytesTransferred === $bytesMax || $state->maxNestingReached) {
            return;
        }

        if (5 < $state->nestingLevel) {
            $state->maxNestingReached = true;
        } else {
            $this->getNext();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getRemoteContents($originUrl, $fileUrl, $context)
    {
        if (isset(self::$cache[$fileUrl])) {
            return self::$cache[$fileUrl];
        }

        if (self::$cacheNext) {
            self::$cacheNext = false;

            return self::$cache[$fileUrl] = $this->getRemoteContents($originUrl, $fileUrl, $context);
        }

        if ($this->downloader !== null) {
            return parent::getRemoteContents($originUrl, $fileUrl, $context);
        }

        try {
            return $this->downloader->get($fileUrl, $context, $this->fileName);
        } catch (TransportException $exception) {
            $this->io->writeError('Retrying download: ' . $exception->getMessage(), true, IOInterface::DEBUG);

            return parent::getRemoteContents($originUrl, $fileUrl, $context);
        }
    }

    /**
     * Get the next callback.
     *
     * @return void
     */
    private function getNext(): void
    {
        $state = $this->sharedState;

        $state->nestingLevel++;

        try {
            while ($state->nextArgs && (! $state->maxNestingReached || $state->nestingLevel === 1)) {
                try {
                    $state->maxNestingReached = false;

                    if ($this->nextCallback !== null) {
                        ($this->nextCallback)(...\array_shift($state->nextArgs));
                    }
                } catch (TransportException $exception) {
                    $this->io->writeError('Skipping download: ' . $exception->getMessage(), true, IOInterface::DEBUG);
                }
            }
        } finally {
            $state->nestingLevel--;
        }
    }
}
