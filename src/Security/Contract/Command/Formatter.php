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

namespace Narrowspark\Automatic\Security\Contract\Command;

use Symfony\Component\Console\Style\SymfonyStyle;

interface Formatter
{
    /**
     * Displays a security report.
     *
     * @param array $vulnerabilities An array of vulnerabilities
     */
    public function displayResults(SymfonyStyle $output, array $vulnerabilities): void;
}
