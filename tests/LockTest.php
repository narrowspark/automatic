<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Narrowspark\Discovery\Lock;
use PHPUnit\Framework\TestCase;

class LockTest extends TestCase
{
    /**
     * @var \Narrowspark\Discovery\Lock
     */
    private $lock;

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        \unlink(__DIR__ . '/test.lock');
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

    public function testRemove(): void
    {
        $this->lock->add('testRemove', ['version' => '2']);

        $this->lock->remove('testRemove');

        self::assertFalse($this->lock->has('testRemove'));
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
}
