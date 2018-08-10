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
        $message = <<<'PHP'
    Do you want to execute this package [%s]?
    [<comment>y</comment>] Yes
    [<comment>n</comment>] No
    [<comment>a</comment>] Yes for all packages, only for the current installation session
    [<comment>p</comment>] Yes permanently, never ask again for this project
    (defaults to <comment>n</comment>): 
PHP;

        if ($url === null) {
            return \sprintf($message, $name);
        }

        return \sprintf("    Review the package from %s.\n" . $message, \str_replace('.git', '', $url), $name);
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
            throw new InvalidArgumentException('Invalid choice');
        }

        return $value;
    }
}
