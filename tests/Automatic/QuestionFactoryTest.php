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

        $this->assertNotEmpty($message);
        $this->assertContains($url, $message);
        $this->assertContains($name, $message);
    }

    public function testGetPackageQuestionWithoutUrl(): void
    {
        $name = 'foo/bar';
        $url  = 'www.example.com';

        $message = QuestionFactory::getPackageQuestion($name, null);

        $this->assertNotEmpty($message);
        $this->assertNotContains($url, $message);
        $this->assertContains($name, $message);
    }

    public function testGetPackageScriptsQuestion(): void
    {
        $name = 'foo/bar';

        $message = QuestionFactory::getPackageScriptsQuestion($name);

        $this->assertNotEmpty($message);
        $this->assertContains($name, $message);
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
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid choice');

        $this->assertSame('n', QuestionFactory::validatePackageQuestionAnswer('0'));
    }
}
