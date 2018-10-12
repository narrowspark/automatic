<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;

final class QuestionFactory
{
    /**
     * Returns the questions for package install.
     *
     * @param string      $name
     * @param null|string $url
     *
     * @return string
     */
    public static function getPackageQuestion(string $name, ?string $url): string
    {
        $message = '    Do you want to execute this package [%s]?' . \PHP_EOL;
        $message .= '     [<comment>y</comment>] Yes' . \PHP_EOL;
        $message .= '    [<comment>n</comment>] No' . \PHP_EOL;
        $message .= '    [<comment>a</comment>] Yes for all packages, only for the current installation session' . \PHP_EOL;
        $message .= '    [<comment>p</comment>] Yes permanently, never ask again for this project' . \PHP_EOL;
        $message .= '    (defaults to <comment>n</comment>): ' . \PHP_EOL;

        if ($url === null) {
            return \sprintf($message, $name);
        }

        return \sprintf('    Review the package from %s.' . \PHP_EOL . $message, \str_replace('.git', '', $url), $name);
    }

    /**
     * Returns the questions for package scripts.
     *
     * @param string $name
     *
     * @return string
     */
    public static function getPackageScriptsQuestion(string $name): string
    {
        $message = '    Do you want to add this package [%s] composer scripts?' . \PHP_EOL;
        $message .= '    (defaults to <comment>no</comment>): ' . \PHP_EOL;

        return \sprintf($message, $name);
    }

    /**
     * Validate given input answer.
     *
     * @param null|string $value
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException
     *
     * @return string
     */
    public static function validatePackageQuestionAnswer(?string $value): string
    {
        if ($value === null) {
            return 'n';
        }

        $value = \mb_strtolower($value[0]);

        if (! \in_array($value, ['y', 'n', 'a', 'p'], true)) {
            throw new InvalidArgumentException('Invalid choice.');
        }

        return $value;
    }
}
