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

        $this->assertContains(DummyClass::class, $classes);
        $this->assertContains(DummyClassTwo::class, $classes);
        $this->assertContains(DummyClassNested::class, $classes);
        $this->assertContains(FooTrait::class, $classes);
        $this->assertContains(AbstractClass::class, $classes);
    }

    public function testWithEmptyFolder(): void
    {
        $dir      = __DIR__ . '/Fixtures/empty';
        $filePath = $dir . '/empty.php';

        \mkdir($dir);
        \touch($filePath);

        $classes = ClassFinder::find($dir);

        $this->assertSame([], $classes);

        \unlink($filePath);
        \rmdir($dir);
    }
}
