<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Downloader;

use Narrowspark\Automatic\Security\Contract\Downloader as DownloaderContract;
use Narrowspark\Automatic\Security\Contract\Exception\RuntimeException;
use Narrowspark\Automatic\Security\SecurityPlugin;

abstract class AbstractDownloader implements DownloaderContract
{
    /**
     * The HTTP timeout in seconds.
     *
     * @var int
     */
    protected $timeout = 20;

    /**
     * {@inheritdoc}
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @return string
     */
    protected function getUserAgent(): string
    {
        return \sprintf(
            'Narrowspark-Security-Audit/%s (%s; %s; %s%s)',
            SecurityPlugin::VERSION,
            \function_exists('php_uname') ? \php_uname('s') : 'Unknown',
            \function_exists('php_uname') ? \php_uname('r') : 'Unknown',
            'PHP ' . \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION . '.' . \PHP_RELEASE_VERSION,
            \getenv('CI') !== false ? '; CI' : ''
        );
    }

    /**
     * Check response status.
     *
     * @param int    $statusCode
     * @param string $body
     *
     * @throws \Narrowspark\Automatic\Security\Contract\Exception\RuntimeException
     *
     * @return void
     */
    protected function checkStatus(int $statusCode, string $body): void
    {
        if ($statusCode === 400) {
            throw new RuntimeException($body);
        }

        if ($statusCode !== 200) {
            throw new RuntimeException(\sprintf('The web service failed for an unknown reason (HTTP %s).', $statusCode), $statusCode);
        }
    }
}
