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

namespace Narrowspark\Automatic\Security\Downloader;

use Composer\CaBundle\CaBundle;
use Composer\Util\StreamContextFactory;
use Narrowspark\Automatic\Security\Contract\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use function error_get_last;
use function error_reporting;
use function file_get_contents;
use function is_dir;
use function is_link;
use function method_exists;
use function preg_match;
use function readlink;
use function sprintf;
use function trim;

final class ComposerDownloader extends AbstractDownloader
{
    /**
     * {@inheritdoc}
     */
    public function download(string $url): string
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true,
                'follow_location' => true,
                'max_redirects' => 3,
                'timeout' => $this->timeout,
                'user_agent' => $this->getUserAgent(),
            ],
            'ssl' => [
                'verify_peer' => 1,
                'verify_host' => 2,
            ],
        ];

        $caPathOrFile = CaBundle::getSystemCaRootBundlePath();
        $filesystem = new Filesystem();

        if (is_dir($caPathOrFile) || (is_link($caPathOrFile) && is_dir((string) (method_exists($filesystem, 'readlink') ? $filesystem->readlink($caPathOrFile) : readlink($caPathOrFile))))) {
            $opts['ssl']['capath'] = $caPathOrFile;
        } else {
            $opts['ssl']['cafile'] = $caPathOrFile;
        }

        $context = StreamContextFactory::getContext($url, $opts);
        $level = error_reporting(0);
        $body = file_get_contents($url, false, $context);

        error_reporting($level);

        if ($body === false) {
            $error = error_get_last();

            throw new RuntimeException(sprintf('An error occurred: %s.', $error['message']));
        }

        // status code
        if ((bool) preg_match('{HTTP/\d\.\d (\d+) }i', $http_response_header[0], $match) === false) {
            throw new RuntimeException('An unknown error occurred.');
        }

        $this->checkStatus((int) $match[1], $body);

        return trim($body);
    }
}
