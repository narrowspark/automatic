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

use Composer\Composer;
use Mockery;
use Narrowspark\Automatic\Common\AbstractContainer;
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Common\AbstractContainer
 *
 * @medium
 */
final class ContainerTest extends MockeryTestCase
{
    /** @var \Narrowspark\Automatic\Common\AbstractContainer */
    private $container;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new DummyContainer([
            Composer::class => static function (AbstractContainer $container) {
                return Mockery::mock(Composer::class);
            },
            'vendor-dir' => static function (): string {
                return '/vendor';
            },
        ]);
    }

    /**
     * @dataProvider provideContainerInstancesCases
     *
     * @param class-string<object>|mixed[] $expected
     */
    public function testContainerInstances(string $key, $expected): void
    {
        $value = $this->container->get($key);

        if (\is_string($value) || (\is_array($value) && \is_array($expected))) {
            self::assertSame($expected, $value);
        }

        if (\is_object($value) && \is_string($expected)) {
            self::assertInstanceOf($expected, $value);
        }
    }

    /**
     * @return array<int, array<int|string, mixed>|string>
     */
    public static function provideContainerInstancesCases(): iterable
    {
        return [
            [Composer::class, Composer::class],
        ];
    }

    public function testGetThrowException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Identifier [test] is not defined.');

        $this->container->get('test');
    }

    public function testGetCache(): void
    {
        self::assertSame('/vendor', $this->container->get('vendor-dir'));

        $this->container->set('vendor-dir', static function (): string {
            return 'test';
        });

        self::assertSame('/vendor', $this->container->get('vendor-dir'));
    }

    public function testGetAll(): void
    {
        self::assertCount(2, $this->container->getAll());
    }
}

class DummyContainer extends AbstractContainer
{
}
