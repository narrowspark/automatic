<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Narrowspark\Discovery\ClassFinder;
use Narrowspark\Discovery\Test\Fixtures\Finder\AbstractClass;
use Narrowspark\Discovery\Test\Fixtures\Finder\DummyClass;
use Narrowspark\Discovery\Test\Fixtures\Finder\DummyClassTwo;
use Narrowspark\Discovery\Test\Fixtures\Finder\FooTrait;
use Narrowspark\Discovery\Test\Fixtures\Finder\Nested\DummyClassNested;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ClassFinderTest extends TestCase
{
    public function testItFindsAllClassesInDirectoryWithGivenNamespace(): void
    {
        $classes = ClassFinder::find(__DIR__ . '/Fixtures/Finder');

        static::assertContains(DummyClass::class, $classes);
        static::assertContains(DummyClassTwo::class, $classes);
        static::assertContains(DummyClassNested::class, $classes);
        static::assertContains(FooTrait::class, $classes);
        static::assertContains(AbstractClass::class, $classes);
    }

    public function testWithEmptyFolder(): void
    {
        $dir      = __DIR__ . '/Fixtures/empty';
        $filePath = $dir . '/empty.php';

        \mkdir($dir);
        \touch($filePath);

        $classes = ClassFinder::find($dir);

        static::assertSame([], $classes);

        \unlink($filePath);
        \rmdir($dir);
    }
}
