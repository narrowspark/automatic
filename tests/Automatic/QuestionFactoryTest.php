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

namespace Narrowspark\Automatic\Test;

use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\QuestionFactory;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
final class QuestionFactoryTest extends TestCase
{
    public function testGetPackageQuestion(): void
    {
        $name = 'foo/bar';
        $url = 'www.example.com';

        $message = QuestionFactory::getPackageQuestion($name, $url);

        self::assertNotEmpty($message);
        self::assertStringContainsString($url, $message);
        self::assertStringContainsString($name, $message);
    }

    public function testGetPackageQuestionWithoutUrl(): void
    {
        $name = 'foo/bar';
        $url = 'www.example.com';

        $message = QuestionFactory::getPackageQuestion($name, null);

        self::assertNotEmpty($message);
        self::assertStringNotContainsString($url, $message);
        self::assertStringContainsString($name, $message);
    }

    public function testGetPackageScriptsQuestion(): void
    {
        $name = 'foo/bar';

        $message = QuestionFactory::getPackageScriptsQuestion($name);

        self::assertNotEmpty($message);
        self::assertStringContainsString($name, $message);
    }

    public function testValidatePackageQuestionAnswer(): void
    {
        self::assertSame('n', QuestionFactory::validatePackageQuestionAnswer(null));
        self::assertSame('n', QuestionFactory::validatePackageQuestionAnswer('n'));
        self::assertSame('y', QuestionFactory::validatePackageQuestionAnswer('y'));
        self::assertSame('a', QuestionFactory::validatePackageQuestionAnswer('a'));
        self::assertSame('p', QuestionFactory::validatePackageQuestionAnswer('p'));
    }

    public function testValidatePackageQuestionAnswerThrowException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid choice');

        self::assertSame('n', QuestionFactory::validatePackageQuestionAnswer('0'));
    }
}
