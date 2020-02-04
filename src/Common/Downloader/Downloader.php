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

use Composer\Cache as ComposerCache;
use Composer\Composer;
use Composer\Downloader\TransportException;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use ErrorException;
use Exception;
use Narrowspark\Automatic\Common\Contract\Exception\RuntimeException;

/**
 * Ported from symfony flex, see original.
 *
 * @see https://github.com/symfony/flex/blob/master/src/Downloader.php
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 */
class Downloader
{
    const RETRIES = 3;

    /** @var string */
    private $endpoint;

    /** @var \Composer\IO\IOInterface */
    private $io;

    /** @var string */
    private $sess;

    /** @var \Composer\Cache */
    private $cache;

    /** @var \Narrowspark\Automatic\Common\Downloader\ParallelDownloader */
    private $rfs;

    /** @var bool */
    private $degradedMode = false;

    /** @var bool */
    private $enabled = true;

    /**
     * Create a new Downloader instance.
     *
     * @param \Narrowspark\Automatic\Common\Downloader\ParallelDownloader $rfs
     *
     * @throws Exception
     */
    public function __construct(string $endpoint, Composer $composer, IoInterface $io, ParallelDownloader $rfs)
    {
        $this->endpoint = $endpoint;
        $this->io = $io;
        $config = $composer->getConfig();
        $this->rfs = $rfs;
        $this->cache = new ComposerCache(
            $io,
            $config->get('cache-repo-dir') . \DIRECTORY_SEPARATOR . \preg_replace('{[^a-z0-9.]}i', '-', $this->endpoint)
        );
        $this->sess = \bin2hex(\random_bytes(16));
    }

    /**
     * Check if the downloader is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Disable the downloader.
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Decodes a JSON HTTP response body.
     *
     * @param string                    $path    The path to get on the server
     * @param array<int|string, string> $headers An array of HTTP headers
     *
     * @return \Narrowspark\Automatic\Common\Downloader\JsonResponse
     */
    public function get(string $path, array $headers = [], bool $cache = true): JsonResponse
    {
        if (! $this->enabled && ($path !== '/versions.json' || $path !== '/security-advisories.json')) {
            return new JsonResponse([]);
        }

        $headers[] = 'Package-Session: ' . $this->sess;
        $url = $this->endpoint . '/' . \ltrim($path, '/');
        $cacheKey = $cache ? \ltrim($path, '/') : null;

        if ($cacheKey !== null && false !== $contents = $this->cache->read($cacheKey)) {
            $cachedResponse = JsonResponse::fromJson(\json_decode($contents, true));

            if ('' !== $lastModified = $cachedResponse->getHeader('last-modified')) {
                $response = $this->fetchFileIfLastModified($url, $cacheKey, $lastModified, $headers);

                if ($response->getStatusCode() === 304) {
                    $response = new JsonResponse($cachedResponse->getBody(), $response->getOriginalHeaders(), 304);
                }

                return $response;
            }
        }

        return $this->fetchFile($url, $cacheKey, $headers);
    }

    /**
     * @param array<int|string, string> $headers An array of HTTP headers
     *
     * @return \Narrowspark\Automatic\Common\Downloader\JsonResponse
     */
    private function fetchFile(string $url, ?string $cacheKey, array $headers): JsonResponse
    {
        $retries = self::RETRIES;

        while ($retries--) {
            try {
                $json = $this->rfs->getContents($this->endpoint, $url, false, ['http' => ['header' => $headers]]);

                if (\is_bool($json)) {
                    throw new RuntimeException(\sprintf('Content for [%s%s] returned a boolean.', $this->endpoint, $url));
                }

                return $this->parseJson($json, $url, $cacheKey, $this->rfs->getLastHeaders());
            } catch (Exception $exception) {
                if ($exception instanceof TransportException && 404 === $exception->getStatusCode()) {
                    throw $exception;
                }

                if ($retries !== 0) {
                    \usleep(100000);

                    continue;
                }

                if ($cacheKey !== null && $contents = $this->cache->read($cacheKey)) {
                    $this->switchToDegradedMode($exception, $url);

                    return JsonResponse::fromJson(JsonFile::parseJson($contents, $this->cache->getRoot() . $cacheKey));
                }

                throw $exception;
            }
        }
    }

    /**
     * @param array<int|string, string> $headers An array of HTTP headers
     *
     * @return \Narrowspark\Automatic\Common\Downloader\JsonResponse
     */
    private function fetchFileIfLastModified(
        string $url,
        string $cacheKey,
        string $lastModifiedTime,
        array $headers
    ): JsonResponse {
        $headers[] = 'If-Modified-Since: ' . $lastModifiedTime;
        $retries = self::RETRIES;

        while ($retries--) {
            try {
                $json = $this->rfs->getContents($this->endpoint, $url, false, ['http' => ['header' => $headers]]);

                if ($this->rfs->findStatusCode($this->rfs->getLastHeaders()) === 304) {
                    return new JsonResponse(null, $this->rfs->getLastHeaders(), 304);
                }

                if (\is_bool($json)) {
                    throw new RuntimeException(\sprintf('Content for [%s%s] returned a boolean.', $this->endpoint, $url));
                }

                return $this->parseJson($json, $url, $cacheKey, $this->rfs->getLastHeaders());
            } catch (Exception $exception) {
                if ($exception instanceof TransportException && $exception->getStatusCode() === 404) {
                    throw $exception;
                }

                if ($retries !== 0) {
                    \usleep(100000);

                    continue;
                }

                $this->switchToDegradedMode($exception, $url);

                return new JsonResponse(null, [], 304);
            }
        }
    }

    /**
     * @param array<int|string, string> $lastHeaders An array of HTTP headers
     *
     * @throws ErrorException
     *
     * @return \Narrowspark\Automatic\Common\Downloader\JsonResponse
     */
    private function parseJson(string $json, string $url, ?string $cacheKey, array $lastHeaders): JsonResponse
    {
        $data = JsonFile::parseJson($json, $url);

        if (\array_key_exists('warning', $data) && \is_string($data['warning'])) {
            $this->io->writeError('<warning>Warning from ' . $url . ': ' . $data['warning'] . '</>');
        }

        if (\array_key_exists('info', $data) && \is_string($data['info'])) {
            $this->io->writeError('<info>Info from ' . $url . ': ' . $data['info'] . '</>');
        }

        $response = new JsonResponse($data, $lastHeaders);

        if ($cacheKey !== null && $response->getHeader('last-modified') !== '') {
            $this->cache->write($cacheKey, \json_encode($response));
        }

        return $response;
    }

    private function switchToDegradedMode(Exception $exception, string $url): void
    {
        if (! $this->degradedMode) {
            $this->io->writeError('<warning>' . $exception->getMessage() . '</>');
            $this->io->writeError('<warning>' . $url . ' could not be fully loaded, package information was loaded from the local cache and may be out of date</>');
        }

        $this->degradedMode = true;
    }
}
