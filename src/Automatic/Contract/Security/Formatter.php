<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Contract\Security;

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
