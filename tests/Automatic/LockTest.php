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

    public function testAddSubWithNotFoundMainKey(): void
    {
        $this->lock->addSub('test', 'providers', ['version' => '1']);

        static::assertTrue($this->lock->has('test', 'providers'));
    }

    public function testAddSub(): void
    {
        $this->lock->add('test2', ['providers' => ['version' => 1]]);
        $this->lock->addSub('test2', 'providers', ['test' => '1']);

        static::assertTrue($this->lock->has('test2', 'providers'));
    }

    public function testRemove(): void
    {
        $this->lock->add('testRemove', ['version' => '2']);

        $this->lock->remove('testRemove');

        static::assertFalse($this->lock->has('testRemove'));
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

        static::assertFalse($this->lock->has('automatic', 'providers'));
    }

    public function testHasWithMainKey(): void
    {
        static::assertFalse($this->lock->has('automatic', 'providers'));
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

    public function testGetWithMainKey(): void
    {
        $providers = [
            'Viserio\\Component\\Console\\Provider\\ConsoleServiceProvider' => [
                'global',
            ],
        ];

        $this->lock->add('automatic', ['providers' => $providers]);

        static::assertSame($providers, $this->lock->get('automatic', 'providers'));
    }

    public function testReset(): void
    {
        $this->lock->add('test', ['version' => '1']);
        $this->lock->reset();

        static::assertFalse($this->lock->has('test'));
    }
}
