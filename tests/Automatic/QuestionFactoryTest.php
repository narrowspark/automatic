<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\QuestionFactory;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class QuestionFactoryTest extends TestCase
{
    public function testGetPackageQuestion(): void
    {
        static::assertSame(
            \str_replace("\n", \PHP_EOL, '    Review the package from www.example.com.
    Do you want to execute this package [foo/bar]?
    [<comment>y</comment>] Yes
    [<comment>n</comment>] No
    [<comment>a</comment>] Yes for all packages, only for the current installation session
    [<comment>p</comment>] Yes permanently, never ask again for this project
    (defaults to <comment>n</comment>): '),
            QuestionFactory::getPackageQuestion('foo/bar', 'www.example.com')
        );
    }

    public function testGetPackageQuestionWithoutUrl(): void
    {
        static::assertSame(
            \str_replace("\n", \PHP_EOL, '    Do you want to execute this package [foo/bar]?
    [<comment>y</comment>] Yes
    [<comment>n</comment>] No
    [<comment>a</comment>] Yes for all packages, only for the current installation session
    [<comment>p</comment>] Yes permanently, never ask again for this project
    (defaults to <comment>n</comment>): '),
            QuestionFactory::getPackageQuestion('foo/bar', null)
        );
    }

    public function testValidatePackageQuestionAnswer(): void
    {
        static::assertSame('n', QuestionFactory::validatePackageQuestionAnswer(null));
        static::assertSame('n', QuestionFactory::validatePackageQuestionAnswer('n'));
        static::assertSame('y', QuestionFactory::validatePackageQuestionAnswer('y'));
        static::assertSame('a', QuestionFactory::validatePackageQuestionAnswer('a'));
        static::assertSame('p', QuestionFactory::validatePackageQuestionAnswer('p'));
    }

    public function testValidatePackageQuestionAnswerThrowException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid choice');

        static::assertSame('n', QuestionFactory::validatePackageQuestionAnswer('0'));
    }
}
