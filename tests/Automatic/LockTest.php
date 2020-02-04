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

namespace Narrowspark\Automatic\Test;

use Narrowspark\Automatic\Lock;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Lock
 *
 * @medium
 */
final class LockTest extends TestCase
{
    /** @var \Narrowspark\Automatic\Lock */
    private $lock;

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        @\unlink(__DIR__ . '/test.lock');
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->lock = new Lock(__DIR__ . '/test.lock');
    }

    public function testAdd(): void
    {
        $this->lock->add('test', ['version' => '1']);

        self::assertTrue($this->lock->has('test'));
    }

    public function testAddSubWithNotFoundMainKey(): void
    {
        $this->lock->addSub('test', 'providers', ['version' => '1']);

        self::assertTrue($this->lock->has('test', 'providers'));
    }

    public function testAddSub(): void
    {
        $this->lock->add('test2', ['providers' => ['version' => 1]]);
        $this->lock->addSub('test2', 'providers', ['test' => '1']);

        self::assertTrue($this->lock->has('test2', 'providers'));
    }

    public function testRemove(): void
    {
        $this->lock->add('testRemove', ['version' => '2']);

        $this->lock->remove('testRemove');

        self::assertFalse($this->lock->has('testRemove'));
    }

    public function testRemoveWithMainKey(): void
    {
        $providers = [
            'Viserio\\Component\\Console\\Provider\\ConsoleServiceProvider' => [
                'global',
            ],
        ];

        $this->lock->add('automatic', ['providers' => $providers]);
        $this->lock->remove('automatic', 'providers');

        self::assertFalse($this->lock->has('automatic', 'providers'));
    }

    public function testHasWithMainKey(): void
    {
        self::assertFalse($this->lock->has('automatic', 'providers'));
    }

    public function testWriteAndRead(): void
    {
        $this->lock->write();

        self::assertCount(0, $this->lock->read());

        $this->lock->add('tests', ['version' => '3']);
        $this->lock->write();

        self::assertCount(1, $this->lock->read());
    }

    public function testGet(): void
    {
        $execptedArray = ['version' => '1'];

        $this->lock->add('test', $execptedArray);

        $execptedHash = 'f5s6daf2s8d6a51f6a9s';

        $this->lock->add('hash', $execptedHash);

        self::assertSame($execptedArray, $this->lock->get('test'));
        self::assertSame($execptedHash, $this->lock->get('hash'));
        self::assertNull($this->lock->get('test2'));
    }

    public function testGetWithMainKey(): void
    {
        $providers = [
            'Viserio\\Component\\Console\\Provider\\ConsoleServiceProvider' => [
                'global',
            ],
        ];

        $this->lock->add('automatic', ['providers' => $providers]);

        self::assertSame($providers, $this->lock->get('automatic', 'providers'));
    }

    public function testReset(): void
    {
        $this->lock->add('test', ['version' => '1']);
        $this->lock->reset();

        self::assertFalse($this->lock->has('test'));
    }
}
