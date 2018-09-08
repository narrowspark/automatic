<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Test;

use Narrowspark\Automatic\Common\ClassFinder;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Common\Test\Fixture\Finder\AbstractClass;
use Narrowspark\Automatic\Common\Test\Fixture\Finder\DummyClass;
use Narrowspark\Automatic\Common\Test\Fixture\Finder\DummyClassTwo;
use Narrowspark\Automatic\Common\Test\Fixture\Finder\DummyInterface;
use Narrowspark\Automatic\Common\Test\Fixture\Finder\FooTrait;
use Narrowspark\Automatic\Common\Test\Fixture\Finder\Nested\DummyClassNested;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ClassFinderTest extends TestCase
{
    use GetGenericPropertyReaderTrait;

    /**
     * A path class loader instance.
     *
     * @var \Narrowspark\Automatic\Common\ClassFinder
     */
    private $loader;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->loader = new ClassFinder(__DIR__);
    }

    public function testItFindsAllClassesInDirectoryWithGivenNamespace(): void
    {
        $this->loader
            ->addPsr4('Fixture/Finder', [''])
            ->find();

        static::assertArrayHasKey(DummyClass::class, $this->loader->getClasses());
        static::assertArrayHasKey(DummyClassTwo::class, $this->loader->getClasses());
        static::assertArrayHasKey(DummyClassNested::class, $this->loader->getClasses());
        static::assertArrayHasKey(FooTrait::class, $this->loader->getTraits());
        static::assertArrayHasKey(AbstractClass::class, $this->loader->getAbstractClasses());
        static::assertArrayHasKey(DummyInterface::class, $this->loader->getInterfaces());
    }

    public function testWithEmptyFolder(): void
    {
        $dir      = __DIR__ . '/Fixture/empty';
        $filePath = $dir . '/empty.php';

        @\mkdir($dir);
        @\touch($filePath);

        $this->loader
            ->addPsr0('/Fixture/empty', [''])
            ->find();

        static::assertSame([], $this->loader->getClasses());
        static::assertSame([], $this->loader->getTraits());
        static::assertSame([], $this->loader->getAbstractClasses());
        static::assertSame([], $this->loader->getInterfaces());

        @\unlink($filePath);
        @\rmdir($dir);
    }

    public function testSetComposerAutoload(): void
    {
        $this->loader->setComposerAutoload(
            'foo/bar',
            [
                'psr-0'                 => [],
                'psr-4'                 => [],
                'classmap'              => [],
                'exclude-from-classmap' => [],
            ]
        );

        $genericPropertyReader = $this->getGenericPropertyReader();

        static::assertSame(
            [
                'psr0'     => [
                    'foo/bar' => [],
                ],
                'psr4'     => [
                    'foo/bar' => [],
                ],
                'classmap' => [
                    'foo/bar' => [],
                ],
            ],
            $genericPropertyReader($this->loader, 'paths')
        );
        static::assertSame([], $genericPropertyReader($this->loader, 'excludes'));
    }

    public function testSetFilter(): void
    {
        $genericPropertyReader = $this->getGenericPropertyReader();

        $this->loader->setFilter(function () {
            return false;
        });

        static::assertInstanceOf(\Closure::class, $genericPropertyReader($this->loader, 'filter'));

        $this->loader
            ->addPsr4('Fixture/Finder', [''])
            ->find();

        static::assertSame([], $this->loader->getAll());
    }
}
