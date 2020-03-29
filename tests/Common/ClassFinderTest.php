<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018-2020 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/automatic
 */

namespace Narrowspark\Automatic\Tests\Common;

use Closure;
use Narrowspark\Automatic\Common\ClassFinder;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Tests\Common\Fixture\Finder\AbstractClass;
use Narrowspark\Automatic\Tests\Common\Fixture\Finder\DummyClass;
use Narrowspark\Automatic\Tests\Common\Fixture\Finder\DummyClassTwo;
use Narrowspark\Automatic\Tests\Common\Fixture\Finder\DummyInterface;
use Narrowspark\Automatic\Tests\Common\Fixture\Finder\FooTrait;
use Narrowspark\Automatic\Tests\Common\Fixture\Finder\Nested\DummyClassNested;
use Narrowspark\Automatic\Tests\Common\Fixture\Finder\StaticFunctionAndClasses;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Common\ClassFinder
 *
 * @medium
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
    protected function setUp(): void
    {
        parent::setUp();

        $this->loader = new ClassFinder(__DIR__);
    }

    public function testItFindsAllClassesInDirectoryWithGivenNamespace(): void
    {
        $this->loader
            ->addPsr4('Fixture/Finder', [''])
            ->find();

        self::assertArrayHasKey(DummyClass::class, $this->loader->getClasses());
        self::assertArrayHasKey(DummyClassTwo::class, $this->loader->getClasses());
        self::assertArrayHasKey(DummyClassNested::class, $this->loader->getClasses());
        self::assertArrayHasKey(StaticFunctionAndClasses::class, $this->loader->getClasses());
        self::assertArrayHasKey(FooTrait::class, $this->loader->getTraits());
        self::assertArrayHasKey(AbstractClass::class, $this->loader->getAbstractClasses());
        self::assertArrayHasKey(DummyInterface::class, $this->loader->getInterfaces());

        self::assertCount(4, $this->loader->getClasses());
        self::assertCount(1, $this->loader->getTraits());
        self::assertCount(1, $this->loader->getAbstractClasses());
        self::assertCount(1, $this->loader->getInterfaces());
    }

    public function testWithEmptyFolder(): void
    {
        $dir = __DIR__ . '/Fixture/empty';
        $filePath = $dir . '/empty.php';

        @\mkdir($dir);
        @\touch($filePath);

        $this->loader
            ->addPsr0('/Fixture/empty', [''])
            ->find();

        self::assertSame([], $this->loader->getClasses());
        self::assertSame([], $this->loader->getTraits());
        self::assertSame([], $this->loader->getAbstractClasses());
        self::assertSame([], $this->loader->getInterfaces());

        @\unlink($filePath);
        @\rmdir($dir);
    }

    public function testSetComposerAutoload(): void
    {
        $this->loader->setComposerAutoload(
            'foo/bar',
            [
                'psr-0' => [],
                'psr-4' => [],
                'classmap' => [],
                'exclude-from-classmap' => [],
            ]
        );

        $genericPropertyReader = $this->getGenericPropertyReader();

        self::assertSame(
            [
                'psr0' => [
                    'foo/bar' => [],
                ],
                'psr4' => [
                    'foo/bar' => [],
                ],
                'classmap' => [
                    'foo/bar' => [],
                ],
            ],
            $genericPropertyReader($this->loader, 'paths')
        );
        self::assertSame([], $genericPropertyReader($this->loader, 'excludes'));
    }

    public function testSetFilter(): void
    {
        $genericPropertyReader = $this->getGenericPropertyReader();

        $this->loader->setFilter(static function (): bool {
            return false;
        });

        self::assertInstanceOf(Closure::class, $genericPropertyReader($this->loader, 'filter'));

        $this->loader
            ->addPsr4('Fixture/Finder', [''])
            ->find();

        self::assertSame([], $this->loader->getAll());
    }
}
