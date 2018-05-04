<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Narrowspark\Discovery\QuestionFactory;
use PHPUnit\Framework\TestCase;

class QuestionFactoryTest extends TestCase
{
    public function testGetPackageQuestion(): void
    {
        self::assertSame(
            '    Review the package from www.example.com.
    Do you want to execute this package?
    [<comment>y</comment>] Yes
    [<comment>n</comment>] No
    [<comment>a</comment>] Yes for all packages, only for the current installation session
    [<comment>p</comment>] Yes permanently, never ask again for this project
    (defaults to <comment>n</comment>): ',
            QuestionFactory::getPackageQuestion('www.example.com')
        );
    }

    public function testValidatePackageQuestionAnswer(): void
    {
        self::assertSame('n', QuestionFactory::validatePackageQuestionAnswer(null));
        self::assertSame('n', QuestionFactory::validatePackageQuestionAnswer('n'));
        self::assertSame('y', QuestionFactory::validatePackageQuestionAnswer('y'));
        self::assertSame('a', QuestionFactory::validatePackageQuestionAnswer('a'));
        self::assertSame('p', QuestionFactory::validatePackageQuestionAnswer('p'));
    }

    /**
     * @expectedException \Narrowspark\Discovery\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid choice
     */
    public function testValidatePackageQuestionAnswerThrowException(): void
    {
        self::assertSame('n', QuestionFactory::validatePackageQuestionAnswer('0'));
    }
}
