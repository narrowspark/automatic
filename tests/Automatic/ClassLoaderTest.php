<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Narrowspark\Automatic\ClassLoader;
use Narrowspark\Automatic\Test\Fixture\Finder\AbstractClass;
use Narrowspark\Automatic\Test\Fixture\Finder\DummyClass;
use Narrowspark\Automatic\Test\Fixture\Finder\DummyClassTwo;
use Narrowspark\Automatic\Test\Fixture\Finder\FooTrait;
use Narrowspark\Automatic\Test\Fixture\Finder\Nested\DummyClassNested;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ClassLoaderTest extends TestCase
{
    /**
     * A path class loader instance.
     *
     * @var \Narrowspark\Automatic\ClassLoader
     */
    private $loader;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->loader = new ClassLoader();
    }

    public function testItFindsAllClassesInDirectoryWithGivenNamespace(): void
    {
        $this->loader->find(__DIR__ . '/Fixture/Finder');

        static::assertArrayHasKey(DummyClass::class, $this->loader->getClasses());
        static::assertArrayHasKey(DummyClassTwo::class, $this->loader->getClasses());
        static::assertArrayHasKey(DummyClassNested::class, $this->loader->getClasses());
        static::assertArrayHasKey(FooTrait::class, $this->loader->getTraits());
        static::assertArrayHasKey(AbstractClass::class, $this->loader->getAbstractClasses());
    }

    public function testWithEmptyFolder(): void
    {
        $dir      = __DIR__ . '/Fixture/empty';
        $filePath = $dir . '/empty.php';

        \mkdir($dir);
        \touch($filePath);

        $this->loader->find($dir);

        static::assertSame([], $this->loader->getClasses());
        static::assertSame([], $this->loader->getTraits());
        static::assertSame([], $this->loader->getAbstractClasses());
        static::assertSame([], $this->loader->getInterfaces());

        \unlink($filePath);
        \rmdir($dir);
    }
}
