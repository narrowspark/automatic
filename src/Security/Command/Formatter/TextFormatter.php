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

final class TextFormatter implements FormatterContract
{
    /**
     * {@inheritdoc}
     */
    public function displayResults(SymfonyStyle $output, array $vulnerabilities): void
    {
        if (\count($vulnerabilities) !== 0) {
            foreach ($vulnerabilities as $dependency => $issues) {
                $output->section(\sprintf('%s (%s)', $dependency, $issues['version']));

                $details = \array_map(static function (array $value): string {
                    $cve = null;

                    if (\array_key_exists('cve', $value) && $value['cve'] !== '') {
                        $cve = $value['cve'];
                    }

                    $link = null;

                    if (\array_key_exists('link', $value) && $value['link'] !== '') {
                        $link = $value['link'];
                    }

                    if ($cve === null) {
                        $cve = '(no CVE ID)';
                    }

                    if ($link === null) {
                        $link = '';
                    }

                    return \sprintf("<info>%s</>: %s\n    %s", $cve, $value['title'], $link);
                }, $issues['advisories']);

                $output->listing($details);
            }
        }
    }
}
