<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Narrowspark\Discovery\ClassFinder;
use Narrowspark\Discovery\Test\Fixtures\Finder\DummyClass;
use Narrowspark\Discovery\Test\Fixtures\Finder\DummyClassTwo;
use Narrowspark\Discovery\Test\Fixtures\Finder\FooTrait;
use Narrowspark\Discovery\Test\Fixtures\Finder\Nested\DummyClassNested;
use PHPUnit\Framework\TestCase;

class ClassFinderTest extends TestCase
{
    public function testItFindsAllClassesInDirectoryWithGivenNamespace(): void
    {
        $classes = ClassFinder::find(__DIR__ . '/Fixtures/Finder');

        self::assertContains(DummyClass::class, $classes);
        self::assertContains(DummyClassTwo::class, $classes);
        self::assertContains(DummyClassNested::class, $classes);
        self::assertContains(FooTrait::class, $classes);
    }

    public function testWithEmptyFolder(): void
    {
        $dir      = __DIR__ . '/Fixtures/empty';
        $filePath = $dir . '/empty.php';

        \mkdir($dir);
        \touch($filePath);

        $classes = ClassFinder::find($dir);

        self::assertSame([], $classes);

        \unlink($filePath);
        \rmdir($dir);
    }
}
