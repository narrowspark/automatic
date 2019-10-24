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

namespace Narrowspark\Automatic\Security\Command\Formatter;

use Narrowspark\Automatic\Security\Contract\Command\Formatter as FormatterContract;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SimpleFormatter implements FormatterContract
{
    /**
     * {@inheritdoc}
     */
    public function displayResults(SymfonyStyle $output, array $vulnerabilities): void
    {
        if (\count($vulnerabilities) !== 0) {
            foreach ($vulnerabilities as $dependency => $issues) {
                $dependencyFullName = $dependency . ' (' . $issues['version'] . ')';
                $output->writeln('<info>' . $dependencyFullName . "\n" . \str_repeat('-', \strlen($dependencyFullName)) . '</>' . "\n");

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
