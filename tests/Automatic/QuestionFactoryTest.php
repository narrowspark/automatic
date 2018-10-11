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
        $name = 'foo/bar';
        $url  = 'www.example.com';

        $message = QuestionFactory::getPackageQuestion($name, $url);

        static::assertNotEmpty($message);
        static::assertContains($url, $message);
        static::assertContains($name, $message);
    }

    public function testGetPackageQuestionWithoutUrl(): void
    {
        $name = 'foo/bar';
        $url  = 'www.example.com';

        $message = QuestionFactory::getPackageQuestion($name, null);

        static::assertNotEmpty($message);
        static::assertNotContains($url, $message);
        static::assertContains($name, $message);
    }

    public function testGetPackageScriptsQuestion(): void
    {
        $name = 'foo/bar';

        $message = QuestionFactory::getPackageScriptsQuestion($name);

        static::assertNotEmpty($message);
        static::assertContains($name, $message);
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
