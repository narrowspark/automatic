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

namespace Narrowspark\Automatic\Common\Downloader;

use Composer\Config;
use Composer\Downloader\TransportException;
use Composer\IO\IOInterface;
use Composer\Util\RemoteFilesystem;
use Exception;
use Throwable;
use function count;

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
     * Switch to active the caching.
     *
     * @var bool
     */
    public static $cacheNext = false;

    /**
     * A static cache for the file url.
     *
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
     * @var \Narrowspark\Automatic\Common\Downloader\CurlDownloader
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

    /** @var null|callable */
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

    /**
     * A object with state information.
     *
     * @var null|object
     */
    private $sharedState;

    /**
     * Create a new ParallelDownloader instance.
     */
    public function __construct(IOInterface $io, Config $config, array $options = [], bool $disableTls = false)
    {
        $this->io = $io;

        if (! \method_exists(parent::class, 'getRemoteContents')) {
            $this->io->writeError('Composer >=1.7 not found, downloads will happen in sequence', true, IOInterface::DEBUG);
        // @codeCoverageIgnoreStart
        } elseif (! extension_loaded('curl')) {
            $this->io->writeError('ext-curl not found, downloads will happen in sequence', true, IOInterface::DEBUG);
        } else {
            $this->downloader = new CurlDownloader();
        }
        // @codeCoverageIgnoreEnd

        parent::__construct($io, $config, $options, $disableTls);
    }

    /**
     * Set the next options for the remote filesystem.
     *
     * @return $this
     */
    public function setNextOptions(array $options): self
    {
        $this->nextOptions = parent::getOptions() !== $options ? $options : [];

        return $this;
    }

    /**
     * Returns the headers of the last request.
     *
     * @return mixed|mixed[]
     */
    public function getLastHeaders(): array
    {
        if ($this->lastHeaders !== null) {
            return $this->lastHeaders;
        }

        if (null !== $lastHeaders = parent::getLastHeaders()) {
            return $lastHeaders;
        }

        return [];
    }

    /**
     * Parallel download for providers.
     */
    public function download(array &$nextArgs, callable $nextCallback, bool $quiet = true, bool $progress = true): void
    {
        $previousState = [$this->quiet, $this->progress, $this->downloadCount, $this->nextCallback, $this->sharedState];

        $this->quiet = $quiet;
        $this->progress = $progress;
        $this->downloadCount = \count($nextArgs);
        $this->nextCallback = $nextCallback;
        $this->sharedState = (object) [
            'bytesMaxCount' => 0,
            'bytesMax' => 0,
            'bytesTransferred' => 0,
            'nextArgs' => &$nextArgs,
            'nestingLevel' => 0,
            'maxNestingReached' => false,
            'lastProgress' => 0,
            'lastUpdate' => \microtime(true),
        ];

        if (! $this->quiet) {
            if ($this->downloader === null && \method_exists(RemoteFilesystem::class, 'getRemoteContents')) {
                $this->io->writeError('<warning>Enable the "cURL" PHP extension for faster downloads</warning>');
            }

            $note = '';

            if ($this->io->isDecorated()) {
                $note = \DIRECTORY_SEPARATOR === '\\' ? '' : (false !== \stripos(\PHP_OS, 'darwin') ? '🎵' : '🎶');
                $note .= $this->downloader !== null ? (\DIRECTORY_SEPARATOR !== '\\' ? ' 💨' : '') : '';
            }

            $this->io->writeError('');
            $this->io->writeError(\sprintf('<info>Prefetching %d packages</info> %s', $this->downloadCount, $note));
            $this->io->writeError('  - Downloading', false);

            if ($this->progress) {
                $this->io->writeError(' (<comment>0%</comment>)', false);
            }
        }

        try {
            $this->getNext();

            if ($this->quiet) {
                // no-op
            } elseif ($this->progress) {
                $this->io->overwriteError(' (<comment>100%</comment>)');
            } else {
                $this->io->writeError(' (<comment>100%</comment>)');
            }
        } finally {
            if (! $this->quiet) {
                $this->io->writeError('');
            }

            [$this->quiet, $this->progress, $this->downloadCount, $this->nextCallback, $this->sharedState] = $previousState;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed[]
     */
    public function getOptions(): array
    {
        $options = \array_replace_recursive(parent::getOptions(), $this->nextOptions);
        $this->nextOptions = [];

        return $options;
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
     * @throws Exception
     *
     * @return bool|string
     */
    public function copy(
        $originUrl,
        $fileUrl,
        $fileName,
        $progress = true,
        $options = []
    ) {
        $options = \array_replace_recursive($this->nextOptions, $options);
        $this->nextOptions = [];
        $rfs = clone $this;
        $rfs->fileName = $fileName;
        $rfs->progress = $this->progress && $progress;

        try {
            return $rfs->get($originUrl, $fileUrl, $options, $fileName, $rfs->progress);
        } finally {
            $rfs->lastHeaders = null;
            $this->lastHeaders = $rfs->getLastHeaders();
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return bool|string
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
    public function callbackGet(
        $notificationCode,
        $severity,
        $message,
        $messageCode,
        $bytesTransferred,
        $bytesMax,
        $nativeDownload = true
    ): void {
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
            $progress = $state->bytesMaxCount / $this->downloadCount = 0;
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
     *
     * @return bool|mixed|string
     */
    protected function getRemoteContents($originUrl, $fileUrl, $context, ?array &$responseHeaders = null)
    {
        if (isset(self::$cache[$fileUrl])) {
            $result = self::$cache[$fileUrl];

            if (3 < \func_num_args()) {
                [$responseHeaders, $result] = $result;
            }

            return $result;
        }

        if (self::$cacheNext) {
            self::$cacheNext = false;

            if (3 < \func_num_args()) {
                $result = $this->getRemoteContents($originUrl, $fileUrl, $context, $responseHeaders);

                self::$cache[$fileUrl] = [$responseHeaders, $result];
            } else {
                $result = $this->getRemoteContents($originUrl, $fileUrl, $context);

                self::$cache[$fileUrl] = $result;
            }

            return $result;
        }

        if ($this->downloader === null || \preg_match('/^https?:/', $originUrl) !== 1) {
            return parent::getRemoteContents($originUrl, $fileUrl, $context, $responseHeaders);
        }

        try {
            $result = $this->downloader->get($originUrl, $fileUrl, $context, $this->fileName);

            if (3 < \func_num_args()) {
                [$responseHeaders, $result] = $result;
            }

            return $result;
        } catch (TransportException $exception) {
            $this->io->writeError('Retrying download: ' . $exception->getMessage(), true, IOInterface::DEBUG);

            return parent::getRemoteContents($originUrl, $fileUrl, $context, $responseHeaders);
        } catch (Throwable $e) {
            $responseHeaders = [];

            throw $e;
        }
    }

    /**
     * Get the next callback.
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
