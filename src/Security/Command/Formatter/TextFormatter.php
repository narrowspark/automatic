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

                $details = \array_map(static function (array $value) {
                    return \sprintf('<info>%s</>: %s' . "\n" . '    %s', $value['cve'] ?: '(no CVE ID)', $value['title'], $value['link']);
                }, $issues['advisories']);

                $output->listing($details);
            }
        }
    }
}
