<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
     * @throws \Narrowspark\Automatic\Security\Contract\Exception\RuntimeException
     *
     * @return string
     */
    public function download(string $url): string;
}
