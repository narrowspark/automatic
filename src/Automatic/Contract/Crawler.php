<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Contract;

/**
 * @internal
 */
interface Crawler
{
    /**
     * Checks a Composer lock file.
     *
     * @param string $lock The path to the composer.lock file
     *
     * @return array An array of two items: the number of vulnerabilities and an array of vulnerabilities
     */
    public function check(string $lock): array;

    /**
     * @param int $timeout
     *
     * @return void
     */
    public function setTimeout(int $timeout): void;

    /**
     * @param string $endPoint
     *
     * @return void
     */
    public function setEndPoint(string $endPoint): void;
}
