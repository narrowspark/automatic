<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Narrowspark\Discovery\PathClassLoader;
use Narrowspark\Discovery\Test\Fixtures\Finder\AbstractClass;
use Narrowspark\Discovery\Test\Fixtures\Finder\DummyClass;
use Narrowspark\Discovery\Test\Fixtures\Finder\DummyClassTwo;
use Narrowspark\Discovery\Test\Fixtures\Finder\FooTrait;
use Narrowspark\Discovery\Test\Fixtures\Finder\Nested\DummyClassNested;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PathClassLoaderTest extends TestCase
{
    /**
     * A path class loader instance.
     *
     * @var \Narrowspark\Discovery\PathClassLoader
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

        static::assertContains(DummyClass::class, $this->loader->getClasses());
        static::assertContains(DummyClassTwo::class, $this->loader->getClasses());
        static::assertContains(DummyClassNested::class, $this->loader->getClasses());
        static::assertContains(FooTrait::class, $this->loader->getTraits());
        static::assertContains(AbstractClass::class, $this->loader->getAbstractClasses());
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
