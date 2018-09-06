<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security;

use Composer\CaBundle\CaBundle;
use Composer\Util\StreamContextFactory;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\Contract\Exception\RuntimeException;

/**
 * @internal
 */
final class Downloader
{
    private $timeout = 5;

    public function downloadWithComposer(string $url): string
    {
        $opts = [
            'http' => [
                'method'          => 'GET',
                'ignore_errors'   => true,
                'follow_location' => true,
                'max_redirects'   => 3,
                'timeout'         => $this->timeout,
                'user_agent'      => $this->getUserAgent(),
            ],
            'ssl' => [
                'verify_peer' => 1,
                'verify_host' => 2,
            ],
        ];

        $caPathOrFile = CaBundle::getSystemCaRootBundlePath();

        if (\is_dir($caPathOrFile) || (\is_link($caPathOrFile) && \is_dir(\readlink($caPathOrFile)))) {
            $opts['ssl']['capath'] = $caPathOrFile;
        } else {
            $opts['ssl']['cafile'] = $caPathOrFile;
        }

        $context = StreamContextFactory::getContext($url, $opts);
        $level   = \error_reporting(0);
        $body    = \file_get_contents($url, false, $context);

        \error_reporting($level);

        if ($body === false) {
            $error = \error_get_last();

            throw new RuntimeException(\sprintf('An error occurred: %s.', $error['message']));
        }

        // status code
        if (! \preg_match('{HTTP/\d\.\d (\d+) }i', $http_response_header[0], $match)) {
            throw new RuntimeException('An unknown error occurred.');
        }

        $this->checkStatus((int) $match[1], $body);

        return \trim($body);
    }

    /**
     * @param string $url
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\RuntimeException
     *
     * @return string
     */
    public function downloadWithCurl(string $url): string
    {
        $curl = \curl_init();

        \curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curl, \CURLOPT_HEADER, true);
        \curl_setopt($curl, \CURLOPT_URL, $url);
        \curl_setopt($curl, \CURLOPT_HTTPHEADER, ['Accept: application/text']);
        \curl_setopt($curl, \CURLOPT_CONNECTTIMEOUT, $this->timeout);
        \curl_setopt($curl, \CURLOPT_TIMEOUT, 10);
        \curl_setopt($curl, \CURLOPT_FOLLOWLOCATION, \ini_get('open_basedir') ? 0 : 1);
        \curl_setopt($curl, \CURLOPT_MAXREDIRS, 3);
        \curl_setopt($curl, \CURLOPT_FAILONERROR, false);
        \curl_setopt($curl, \CURLOPT_SSL_VERIFYPEER, 1);
        \curl_setopt($curl, \CURLOPT_SSL_VERIFYHOST, 2);
        \curl_setopt($curl, \CURLOPT_USERAGENT, $this->getUserAgent());

        $caPathOrFile = CaBundle::getSystemCaRootBundlePath();

        if (\is_dir($caPathOrFile) || (\is_link($caPathOrFile) && \is_dir(\readlink($caPathOrFile)))) {
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

        $body       = \mb_substr($response, \curl_getinfo($curl, \CURLINFO_HEADER_SIZE));
        $statusCode = (int) \curl_getinfo($curl, \CURLINFO_HTTP_CODE);

        \curl_close($curl);

        $this->checkStatus($statusCode, $body);

        return \trim($body);
    }

    /**
     * @return string
     */
    private function getUserAgent(): string
    {
        return \sprintf(
            'Narrowspark-Automatic/%s (%s; %s; %s%s)',
            Automatic::VERSION === '@package_version@' ? 'source' : Automatic::VERSION,
            \function_exists('php_uname') ? \php_uname('s') : 'Unknown',
            \function_exists('php_uname') ? \php_uname('r') : 'Unknown',
            'PHP ' . \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION . '.' . \PHP_RELEASE_VERSION,
            \getenv('CI') ? '; CI' : ''
        );
    }

    /**
     * Check response status.
     *
     * @param int    $statusCode
     * @param string $body
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\RuntimeException
     *
     * @return void
     */
    private function checkStatus(int $statusCode, string $body): void
    {
        if ($statusCode === 400) {
            throw new RuntimeException($body);
        }

        if ($statusCode !== 200) {
            throw new RuntimeException(\sprintf('The web service failed for an unknown reason (HTTP %s).', $statusCode), $statusCode);
        }
    }
}
