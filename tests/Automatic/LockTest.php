<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Narrowspark\Automatic\Lock;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LockTest extends TestCase
{
    /**
     * @var \Narrowspark\Automatic\Lock
     */
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

        static::assertTrue($this->lock->has('test'));
    }

    public function testRemove(): void
    {
        $this->lock->add('testRemove', ['version' => '2']);

        $this->lock->remove('testRemove');

        static::assertFalse($this->lock->has('testRemove'));
    }

    public function testWriteAndRead(): void
    {
        $this->lock->write();

        static::assertCount(0, $this->lock->read());

        $this->lock->add('tests', ['version' => '3']);
        $this->lock->write();

        static::assertCount(1, $this->lock->read());
    }

    public function testGet(): void
    {
        $execptedArray = ['version' => '1'];

        $this->lock->add('test', $execptedArray);

        $execptedHash = 'f5s6daf2s8d6a51f6a9s';

        $this->lock->add('hash', $execptedHash);

        static::assertSame($execptedArray, $this->lock->get('test'));
        static::assertSame($execptedHash, $this->lock->get('hash'));
        static::assertNull($this->lock->get('test2'));
    }

    public function testClear(): void
    {
        $this->lock->add('test', ['version' => '1']);
        $this->lock->clear();

        static::assertFalse($this->lock->has('test'));
    }
}
