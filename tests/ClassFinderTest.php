<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Narrowspark\Discovery\ClassFinder;
use Narrowspark\Discovery\Test\Fixtures\ComposerJsonFactory;
use Narrowspark\Discovery\Test\Fixtures\Finder\DummyClass;
use Narrowspark\Discovery\Test\Fixtures\Finder\DummyClassTwo;
use Narrowspark\Discovery\Test\Fixtures\Finder\Nested\DummyClassNested;
use PHPUnit\Framework\TestCase;

class ClassFinderTest extends TestCase
{
    /**
     * @var string
     */
    private $dir;

    /**
     * @var string
     */
    private $namespace;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->dir       = __DIR__ . '/Fixtures/Finder';
        $this->namespace = 'Narrowspark\\Discovery\\Test\\Fixtures\Finder\\';
    }

    public function testItFindsAllClassesInDirectoryWithGivenNamespace(): void
    {
        $classes = ClassFinder::find($this->dir, $this->namespace);

        self::assertFoundClassesOnNamespace($classes);
    }

    public function testItSkipsClassesIfNamespaceDoesNotFit(): void
    {
        $classes = ClassFinder::find(__DIR__ . '/Fixtures', $this->namespace);

        self::assertFoundClassesOnNamespace($classes);
        self::assertNotContains(ComposerJsonFactory::class, $classes);
    }

    /**
     * @param array$classes
     */
    private static function assertFoundClassesOnNamespace(array $classes): void
    {
        self::assertContains(DummyClass::class, $classes);
        self::assertContains(DummyClassTwo::class, $classes);
        self::assertContains(DummyClassNested::class, $classes);
    }
}
