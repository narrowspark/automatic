<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Test;

use Narrowspark\Automatic\Common\Contract\Package as ContractPackage;
use Narrowspark\Automatic\Common\Package;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PackageTest extends TestCase
{
    /**
     * @var \Narrowspark\Automatic\Common\Package
     */
    private $package;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->package = new Package('test/Test', '1');
    }

    public function testGetName(): void
    {
        static::assertSame('test/test', $this->package->getName());

        $this->package->setName('test/test2');

        static::assertSame('test/test2', $this->package->getName());
    }

    public function testGetPrettyName(): void
    {
        static::assertSame('test/Test', $this->package->getPrettyName());
    }

    public function testGetPrettyVersion(): void
    {
        static::assertSame('1', $this->package->getPrettyVersion());
    }

    public function testIsDev(): void
    {
        static::assertFalse($this->package->isDev());

        $this->package->setIsDev();

        static::assertTrue($this->package->isDev());
    }

    public function testSetAndGetUrl(): void
    {
        $url = 'https://packagist.org/packages/narrowspark/automatic';

        $this->package->setUrl($url);

        static::assertSame($url, $this->package->getUrl());
    }

    public function testSetAndGetOperation(): void
    {
        $this->package->setOperation(Package::INSTALL_OPERATION);

        static::assertSame(Package::INSTALL_OPERATION, $this->package->getOperation());
    }

    public function testGetType(): void
    {
        $type = 'library';

        $this->package->setType($type);

        static::assertSame($type, $this->package->getType());
    }

    public function testSetAndGetRequire(): void
    {
        $requires = [];

        $this->package->setRequires($requires);

        static::assertSame($requires, $this->package->getRequires());
    }

    public function testSetAndGetConfigs(): void
    {
        $config = ['cut' => true];

        $this->package->setConfig($config);

        static::assertTrue($this->package->getConfig('cut'));
        static::assertEquals($config, $this->package->getConfigs());
    }

    public function testGetConfigWithMainKeyAndName(): void
    {
        $config = ['test' => ['cut' => true]];

        $this->package->setConfig($config);

        static::assertTrue($this->package->getConfig('test', 'cut'));
        static::assertNull($this->package->getConfig('test', 'noop'));
    }

    public function testHasConfigWithMainKeyAndName(): void
    {
        $config = ['test' => ['cut' => true]];

        $this->package->setConfig($config);

        static::assertFalse($this->package->hasConfig('noop'));
        static::assertFalse($this->package->hasConfig('test', 'noop'));
        static::assertFalse($this->package->hasConfig('noop', 'noop'));
        static::assertTrue($this->package->hasConfig('test'));
        static::assertTrue($this->package->hasConfig('test', 'cut'));
    }

    public function testSetAndGetParentName(): void
    {
        $name = 'foo/bar';

        $this->package->setParentName($name);

        static::assertSame($name, $this->package->getParentName());
    }

    public function testSetAndGetTime(): void
    {
        $time = (new \DateTimeImmutable())->format(\DateTime::RFC3339);

        $this->package->setTime($time);

        static::assertSame($time, $this->package->getTime());
    }

    public function testToArray(): void
    {
        $array = $this->package->toArray();

        static::assertSame(
            [
                'pretty-name'                        => 'test/Test',
                'version'                            => '1',
                'parent'                             => null,
                'is-dev'                             => false,
                'url'                                => null,
                'operation'                          => null,
                'type'                               => null,
                'requires'                           => [],
                'automatic-extra'                    => [],
                'created'                            => $this->package->getTime(),
            ],
            $array
        );
    }

    public function testCreateFromLock(): void
    {
        $lockdata = [
            'pretty-name'                        => 'test/Test',
            'version'                            => '1',
            'parent'                             => null,
            'is-dev'                             => false,
            'url'                                => null,
            'operation'                          => null,
            'type'                               => null,
            'requires'                           => [],
            'automatic-extra'                    => [],
            'created'                            => $this->package->getTime(),
        ];

        static::assertInstanceOf(ContractPackage::class, Package::createFromLock('test/test', $lockdata));
    }
}
