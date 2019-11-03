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
use Narrowspark\Automatic\Security\Contract\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use const CURLINFO_HEADER_SIZE;
use const CURLINFO_HTTP_CODE;
use const CURLOPT_CAINFO;
use const CURLOPT_CAPATH;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_FAILONERROR;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADER;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_MAXREDIRS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYHOST;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;
use const CURLOPT_USERAGENT;
use function curl_close;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function ini_get;
use function is_dir;
use function is_link;
use function is_string;
use function sprintf;
use function substr;
use function trim;

final class CurlDownloader extends AbstractDownloader
{
    /**
     * {@inheritdoc}
     */
    public function download(string $url): string
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Accept: application/text']);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, is_string(ini_get('open_basedir')) ? 0 : 1);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 3);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_USERAGENT, $this->getUserAgent());

        $caPathOrFile = CaBundle::getSystemCaRootBundlePath();
        $filesystem = new Filesystem();

        if (is_dir($caPathOrFile) || (is_link($caPathOrFile) && is_dir((string) $filesystem->readlink($caPathOrFile)))) {
            curl_setopt($curl, CURLOPT_CAPATH, $caPathOrFile);
        } else {
            curl_setopt($curl, CURLOPT_CAINFO, $caPathOrFile);
        }

        $response = curl_exec($curl);

        if ($response === false) {
            $error = curl_error($curl);

            curl_close($curl);

            throw new RuntimeException(sprintf('An error occurred: %s.', $error));
        }

        $body = substr((string) $response, curl_getinfo($curl, CURLINFO_HEADER_SIZE));
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        $this->checkStatus($statusCode, $body);

        return trim($body);
    }
}
