<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Security\Downloader;

use Composer\CaBundle\CaBundle;
use Narrowspark\Automatic\Security\Contract\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

final class CurlDownloader extends AbstractDownloader
{
    /**
     * {@inheritdoc}
     */
    public function download(string $url): string
    {
        $curl = \curl_init();

        \curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curl, \CURLOPT_HEADER, true);
        \curl_setopt($curl, \CURLOPT_URL, $url);
        \curl_setopt($curl, \CURLOPT_HTTPHEADER, ['Accept: application/text']);
        \curl_setopt($curl, \CURLOPT_CONNECTTIMEOUT, $this->timeout);
        \curl_setopt($curl, \CURLOPT_TIMEOUT, 10);
        \curl_setopt($curl, \CURLOPT_FOLLOWLOCATION, \is_string(\ini_get('open_basedir')) ? 0 : 1);
        \curl_setopt($curl, \CURLOPT_MAXREDIRS, 3);
        \curl_setopt($curl, \CURLOPT_FAILONERROR, false);
        \curl_setopt($curl, \CURLOPT_SSL_VERIFYPEER, 1);
        \curl_setopt($curl, \CURLOPT_SSL_VERIFYHOST, 2);
        \curl_setopt($curl, \CURLOPT_USERAGENT, $this->getUserAgent());

        $caPathOrFile = CaBundle::getSystemCaRootBundlePath();
        $filesystem = new Filesystem();

        if (\is_dir($caPathOrFile) || (\is_link($caPathOrFile) && \is_dir((string) $filesystem->readlink($caPathOrFile)))) {
            \curl_setopt($curl, \CURLOPT_CAPATH, $caPathOrFile);
        } else {
            \curl_setopt($curl, \CURLOPT_CAINFO, $caPathOrFile);
        }

        $response = \curl_exec($curl);

        if ($response === false) {
            $error = \curl_error($curl);

            \curl_close($curl);

            throw new RuntimeException(\sprintf('An error occurred: %s.', $error));
        }

        $body = \substr((string) $response, \curl_getinfo($curl, \CURLINFO_HEADER_SIZE));
        $statusCode = (int) \curl_getinfo($curl, \CURLINFO_HTTP_CODE);

        \curl_close($curl);

        $this->checkStatus($statusCode, $body);

        return \trim($body);
    }
}
