<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Narrowspark\Discovery\QuestionFactory;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class QuestionFactoryTest extends TestCase
{
    public function testGetPackageQuestion(): void
    {
        $this->assertSame(
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
        $this->assertSame('n', QuestionFactory::validatePackageQuestionAnswer(null));
        $this->assertSame('n', QuestionFactory::validatePackageQuestionAnswer('n'));
        $this->assertSame('y', QuestionFactory::validatePackageQuestionAnswer('y'));
        $this->assertSame('a', QuestionFactory::validatePackageQuestionAnswer('a'));
        $this->assertSame('p', QuestionFactory::validatePackageQuestionAnswer('p'));
    }

    public function testValidatePackageQuestionAnswerThrowException(): void
    {
        $this->expectException(\Narrowspark\Discovery\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid choice');

        $this->assertSame('n', QuestionFactory::validatePackageQuestionAnswer('0'));
    }
}
