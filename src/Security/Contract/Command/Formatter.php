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

namespace Narrowspark\Automatic\Security\Contract\Command;

use Symfony\Component\Console\Style\SymfonyStyle;

interface Formatter
{
    /**
     * Displays a security report.
     *
     * @param \Symfony\Component\Console\Style\SymfonyStyle $output
     * @param array                                         $vulnerabilities An array of vulnerabilities
     *
     * @return void
     */
    public function displayResults(SymfonyStyle $output, array $vulnerabilities): void;
}
