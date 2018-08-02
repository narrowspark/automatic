<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;

final class QuestionFactory
{
    /**
     * Returns the questions for package install.
     *
     * @param string $url
     *
     * @return string
     */
    public static function getPackageQuestion(string $url): string
    {
        return \sprintf('    Review the package from %s.
    Do you want to execute this package?
    [<comment>y</comment>] Yes
    [<comment>n</comment>] No
    [<comment>a</comment>] Yes for all packages, only for the current installation session
    [<comment>p</comment>] Yes permanently, never ask again for this project
    (defaults to <comment>n</comment>): ', $url);
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
