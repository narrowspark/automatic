<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Installer;

use Narrowspark\Discovery\Installer\ConfiguratorClassFinder;
use Narrowspark\Discovery\Test\Fixtures\Finder\DummyClass;
use Narrowspark\Discovery\Test\Fixtures\Finder\DummyClassTwo;
use Narrowspark\Discovery\Test\Fixtures\Finder\Nested\DummyClassNested;
use PHPUnit\Framework\TestCase;

class ConfiguratorClassFinderTest extends TestCase
{
    public function testItFindsAllClassesInDirectoryWithGivenNamespace(): void
    {
        $classes = ConfiguratorClassFinder::find(__DIR__ . '/../Fixtures/Finder');

        self::assertContains(DummyClass::class, $classes);
        self::assertContains(DummyClassTwo::class, $classes);
        self::assertContains(DummyClassNested::class, $classes);
    }

    public function testWithEmptyFolder(): void
    {
        $dir      = __DIR__ . '/../Fixtures/empty';
        $filePath = $dir . '/empty.php';

        \mkdir($dir);
        \touch($filePath);

        $classes = ConfiguratorClassFinder::find($dir);

        self::assertSame([], $classes);

        \unlink($filePath);
        \rmdir($dir);
    }
}
