<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Test\Common;

use Narrowspark\Automatic\Common\Contract\Package as ContractPackage;
use Narrowspark\Automatic\Common\Package;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
final class PackageTest extends TestCase
{
    /** @var \Narrowspark\Automatic\Common\Package */
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
        self::assertSame('test/test', $this->package->getName());

        $this->package->setName('test/test2');

        self::assertSame('test/test2', $this->package->getName());
    }

    public function testGetPrettyName(): void
    {
        self::assertSame('test/Test', $this->package->getPrettyName());
    }

    public function testGetPrettyVersion(): void
    {
        self::assertSame('1', $this->package->getPrettyVersion());
    }

    public function testIsDev(): void
    {
        self::assertFalse($this->package->isDev());

        $this->package->setIsDev();

        self::assertTrue($this->package->isDev());
    }

    public function testSetAndGetUrl(): void
    {
        $url = 'https://packagist.org/packages/narrowspark/automatic';

        $this->package->setUrl($url);

        self::assertSame($url, $this->package->getUrl());
    }

    public function testSetAndGetOperation(): void
    {
        $this->package->setOperation(Package::INSTALL_OPERATION);

        self::assertSame(Package::INSTALL_OPERATION, $this->package->getOperation());
    }

    public function testGetType(): void
    {
        $type = 'library';

        $this->package->setType($type);

        self::assertSame($type, $this->package->getType());
    }

    public function testSetAndGetRequire(): void
    {
        $requires = [];

        $this->package->setRequires($requires);

        self::assertSame($requires, $this->package->getRequires());
    }

    public function testSetAndGetConfigs(): void
    {
        $config = ['cut' => true];

        $this->package->setConfig($config);

        self::assertTrue($this->package->getConfig('cut'));
        self::assertEquals($config, $this->package->getConfigs());
    }

    public function testGetConfigWithMainKeyAndName(): void
    {
        $config = ['test' => ['cut' => true]];

        $this->package->setConfig($config);

        self::assertTrue($this->package->getConfig('test', 'cut'));
        self::assertNull($this->package->getConfig('test', 'noop'));
    }

    public function testHasConfigWithMainKeyAndName(): void
    {
        $config = ['test' => ['cut' => true]];

        $this->package->setConfig($config);

        self::assertFalse($this->package->hasConfig('noop'));
        self::assertFalse($this->package->hasConfig('test', 'noop'));
        self::assertFalse($this->package->hasConfig('noop', 'noop'));
        self::assertTrue($this->package->hasConfig('test'));
        self::assertTrue($this->package->hasConfig('test', 'cut'));
    }

    public function testSetAndGetParentName(): void
    {
        $name = 'foo/bar';

        $this->package->setParentName($name);

        self::assertSame($name, $this->package->getParentName());
    }

    public function testSetAndGetTime(): void
    {
        $time = (new \DateTimeImmutable())->format(\DateTime::RFC3339);

        $this->package->setTime($time);

        self::assertSame($time, $this->package->getTime());
    }

    public function testSetAndGetAutoload(): void
    {
        $array = [
            'psr-4' => [
                'Narrowspark\\Automatic\\Common\\Test\\' => 'tests/Common/',
                'Narrowspark\\Automatic\\Test\\' => 'tests/Automatic/',
            ],
        ];

        $this->package->setAutoload($array);

        self::assertSame($array, $this->package->getAutoload());
    }

    public function testToArray(): void
    {
        $array = $this->package->toArray();

        self::assertSame(
            [
                'pretty-name' => 'test/Test',
                'version' => '1',
                'parent' => null,
                'is-dev' => false,
                'url' => null,
                'operation' => null,
                'type' => null,
                'requires' => [],
                'automatic-extra' => [],
                'autoload' => [],
                'created' => $this->package->getTime(),
            ],
            $array
        );
    }

    public function testCreateFromLock(): void
    {
        $lockdata = [
            'pretty-name' => 'test/Test',
            'version' => '1',
            'parent' => null,
            'is-dev' => false,
            'url' => null,
            'operation' => null,
            'type' => null,
            'requires' => [],
            'automatic-extra' => [],
            'created' => $this->package->getTime(),
        ];

        self::assertInstanceOf(ContractPackage::class, Package::createFromLock('test/test', $lockdata));
    }
}
