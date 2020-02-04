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

                $output->writeln('<info>' . $dependencyFullName . "\n" . \str_repeat('-', \strlen($dependencyFullName)) . "</>\n");

                foreach ($issues['advisories'] as $issue => $details) {
                    $output->write(' * ');
                    $cve = null;

                    if (\array_key_exists('cve', $details) && $details['cve'] !== '') {
                        $cve = $details['cve'];
                    }

                    $link = null;

                    if (\array_key_exists('link', $details) && $details['link'] !== '') {
                        $link = $details['link'];
                    }

                    if ($cve === null) {
                        $cve = '(no CVE ID)';
                    }

                    if ($link === null) {
                        $link = '';
                    }

                    $output->write('<comment>' . $cve . ': </comment>');
                    $output->writeln($details['title']);
                    $output->writeln('   ' . $link);
                    $output->writeln('');
                }
            }
        }
    }
}
