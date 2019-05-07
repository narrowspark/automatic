<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Command\Formatter;

use Narrowspark\Automatic\Security\Contract\Command\Formatter as FormatterContract;
use Symfony\Component\Console\Style\SymfonyStyle;

class JsonFormatter implements FormatterContract
{
    /**
     * {@inheritDoc}
     */
    public function displayResults(SymfonyStyle $output, array $vulnerabilities): void
    {
        $output->writeln((string) \json_encode($vulnerabilities, \JSON_PRETTY_PRINT));
    }
}
