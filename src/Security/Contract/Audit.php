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

namespace Narrowspark\Automatic\Security\Contract;

use Composer\IO\IOInterface;

interface Audit
{
    /**
     * Set the composer dev mode.
     *
     * @param bool $bool
     */
    public function setDevMode($bool): void;

    /**
     * Checks a package on name and version.
     *
     * @return array[]
     */
    public function checkPackage(string $name, string $version, array $securityAdvisories): array;

    /**
     * Checks a composer lock file.
     *
     * @param string $lock The path to the composer.lock file
     *
     * @throws \Narrowspark\Automatic\Security\Contract\Exception\RuntimeException When the lock file does not exist
     *
     * @return array[]
     */
    public function checkLock(string $lock): array;

    /**
     * Get the news security advisories from narrowspark/security-advisories.
     *
     * @return array<string, array>
     */
    public function getSecurityAdvisories(?IOInterface $io = null): array;
}
