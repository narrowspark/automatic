<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\Automatic\Test\Common;

use Composer\Composer;
use Mockery;
use Narrowspark\Automatic\Common\AbstractContainer;
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use function is_array;
use function is_string;

/**
 * @internal
 *
 * @small
 */
final class ContainerTest extends MockeryTestCase
{
    /** @var \Narrowspark\Automatic\Common\AbstractContainer */
    private static $staticContainer;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$staticContainer = new DummyContainer([
            Composer::class => static function (AbstractContainer $container) {
                return Mockery::mock(Composer::class);
            },
            'vendor-dir' => static function () {
                return '/vendor';
            },
        ]);
    }

    /**
     * @dataProvider provideContainerInstancesCases
     *
     * @param string $key
     * @param mixed  $expected
     *
     * @return void
     */
    public function testContainerInstances(string $key, $expected): void
    {
        $value = self::$staticContainer->get($key);

        if (is_string($value) || is_array($value)) {
            self::assertSame($expected, $value);
        } else {
            self::assertInstanceOf($expected, $value);
        }
    }

    /**
     * @return array
     */
    public function provideContainerInstancesCases(): iterable
    {
        return [
            [Composer::class, Composer::class],
        ];
    }

    public function testGetThrowException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Identifier [test] is not defined.');

        self::$staticContainer->get('test');
    }

    public function testGetCache(): void
    {
        self::assertSame('/vendor', self::$staticContainer->get('vendor-dir'));

        self::$staticContainer->set('vendor-dir', static function () {
            return 'test';
        });

        self::assertSame('/vendor', self::$staticContainer->get('vendor-dir'));
    }

    public function testGetAll(): void
    {
        self::assertCount(2, self::$staticContainer->getAll());
    }
}

class DummyContainer extends AbstractContainer
{
}
