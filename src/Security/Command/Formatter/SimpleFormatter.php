<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Command\Formatter;

use Narrowspark\Automatic\Security\Contract\Command\Formatter as FormatterContract;
use Symfony\Component\Console\Style\SymfonyStyle;

class SimpleFormatter implements FormatterContract
{
    /**
     * {@inheritdoc}
     */
    public function displayResults(SymfonyStyle $output, array $vulnerabilities): void
    {
        if (\count($vulnerabilities) !== 0) {
            foreach ($vulnerabilities as $dependency => $issues) {
                $dependencyFullName = $dependency . ' (' . $issues['version'] . ')';
                $output->writeln('<info>' . $dependencyFullName . "\n" . \str_repeat('-', \mb_strlen($dependencyFullName)) . "</>\n");

                foreach ($issues['advisories'] as $issue => $details) {
                    $output->write(' * ');

                    if ($details['cve']) {
                        $output->write('<comment>' . $details['cve'] . ': </comment>');
                    }

                    $output->writeln($details['title']);

                    if ('' !== $details['link']) {
                        $output->writeln('   ' . $details['link']);
                    }

                    $output->writeln('');
                }
            }
        }
    }
}
