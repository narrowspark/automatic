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

namespace Narrowspark\Automatic;

use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;

final class QuestionFactory
{
    /**
     * Returns the questions for package install.
     */
    public static function getPackageQuestion(string $name, ?string $url): string
    {
        $message = "    Do you want to execute this package [%s]?\n";
        $message .= "     [<comment>y</comment>] Yes\n";
        $message .= "    [<comment>n</comment>] No\n";
        $message .= "    [<comment>a</comment>] Yes for all packages, only for the current installation session\n";
        $message .= "    [<comment>p</comment>] Yes permanently, never ask again for this project\n";
        $message .= "    (defaults to <comment>n</comment>): \n";

        if ($url === null) {
            return \sprintf($message, $name);
        }

        return \sprintf("    Review the package from %s.\n" . $message, \str_replace('.git', '', $url), $name);
    }

    /**
     * Returns the questions for package scripts.
     */
    public static function getPackageScriptsQuestion(string $name): string
    {
        $message = "    Do you want to add this package [%s] composer scripts?\n";
        $message .= "    (defaults to <comment>no</comment>): \n";

        return \sprintf($message, $name);
    }

    /**
     * Validate given input answer.
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException
     */
    public static function validatePackageQuestionAnswer(?string $value): string
    {
        if ($value === null) {
            return 'n';
        }

        $value = \strtolower($value[0]);

        if (! \in_array($value, ['y', 'n', 'a', 'p'], true)) {
            throw new InvalidArgumentException('Invalid choice.');
        }

        return $value;
    }
}
