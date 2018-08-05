<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Narrowspark\Automatic\PathClassLoader;
use Narrowspark\Automatic\Test\Fixtures\Finder\AbstractClass;
use Narrowspark\Automatic\Test\Fixtures\Finder\DummyClass;
use Narrowspark\Automatic\Test\Fixtures\Finder\DummyClassTwo;
use Narrowspark\Automatic\Test\Fixtures\Finder\FooTrait;
use Narrowspark\Automatic\Test\Fixtures\Finder\Nested\DummyClassNested;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PathClassLoaderTest extends TestCase
{
    /**
     * A path class loader instance.
     *
     * @var \Narrowspark\Automatic\PathClassLoader
     */
    private $loader;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->loader = new PathClassLoader();
    }

    public function testItFindsAllClassesInDirectoryWithGivenNamespace(): void
    {
        $this->loader->find(__DIR__ . '/Fixtures/Finder');

        static::assertArrayHasKey(DummyClass::class, $this->loader->getClasses());
        static::assertArrayHasKey(DummyClassTwo::class, $this->loader->getClasses());
        static::assertArrayHasKey(DummyClassNested::class, $this->loader->getClasses());
        static::assertArrayHasKey(FooTrait::class, $this->loader->getTraits());
        static::assertArrayHasKey(AbstractClass::class, $this->loader->getAbstractClasses());
    }

    public function testWithEmptyFolder(): void
    {
        $dir      = __DIR__ . '/Fixtures/empty';
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
