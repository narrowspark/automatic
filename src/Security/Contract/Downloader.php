<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Contract;

interface Downloader
{
    /**
     * Sets the HTTP timeout in seconds.
     *
     * @param int $timeout The HTTP timeout in seconds
     *
     * @return void
     */
    public function setTimeout(int $timeout): void;

    /**
     * Download a file from a url.
     *
     * @param string $url
     *
     * @return string
     */
    public function download(string $url): string;
}
