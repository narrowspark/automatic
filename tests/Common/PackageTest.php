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
        $this->assertSame('test/test', $this->package->getName());

        $this->package->setName('test/test2');

        $this->assertSame('test/test2', $this->package->getName());
    }

    public function testGetPrettyName(): void
    {
        $this->assertSame('test/Test', $this->package->getPrettyName());
    }

    public function testGetPrettyVersion(): void
    {
        $this->assertSame('1', $this->package->getPrettyVersion());
    }

    public function testIsDev(): void
    {
        $this->assertFalse($this->package->isDev());

        $this->package->setIsDev();

        $this->assertTrue($this->package->isDev());
    }

    public function testSetAndGetUrl(): void
    {
        $url = 'https://packagist.org/packages/narrowspark/automatic';

        $this->package->setUrl($url);

        $this->assertSame($url, $this->package->getUrl());
    }

    public function testSetAndGetOperation(): void
    {
        $this->package->setOperation(Package::INSTALL_OPERATION);

        $this->assertSame(Package::INSTALL_OPERATION, $this->package->getOperation());
    }

    public function testGetType(): void
    {
        $type = 'library';

        $this->package->setType($type);

        $this->assertSame($type, $this->package->getType());
    }

    public function testSetAndGetRequire(): void
    {
        $requires = [];

        $this->package->setRequires($requires);

        $this->assertSame($requires, $this->package->getRequires());
    }

    public function testSetAndGetConfigs(): void
    {
        $config = ['cut' => true];

        $this->package->setConfig($config);

        $this->assertTrue($this->package->getConfig('cut'));
        $this->assertEquals($config, $this->package->getConfigs());
    }

    public function testGetConfigWithMainKeyAndName(): void
    {
        $config = ['test' => ['cut' => true]];

        $this->package->setConfig($config);

        $this->assertTrue($this->package->getConfig('test', 'cut'));
        $this->assertNull($this->package->getConfig('test', 'noop'));
    }

    public function testHasConfigWithMainKeyAndName(): void
    {
        $config = ['test' => ['cut' => true]];

        $this->package->setConfig($config);

        $this->assertFalse($this->package->hasConfig('noop'));
        $this->assertFalse($this->package->hasConfig('test', 'noop'));
        $this->assertFalse($this->package->hasConfig('noop', 'noop'));
        $this->assertTrue($this->package->hasConfig('test'));
        $this->assertTrue($this->package->hasConfig('test', 'cut'));
    }

    public function testSetAndGetParentName(): void
    {
        $name = 'foo/bar';

        $this->package->setParentName($name);

        $this->assertSame($name, $this->package->getParentName());
    }

    public function testSetAndGetTime(): void
    {
        $time = (new \DateTimeImmutable())->format(\DateTime::RFC3339);

        $this->package->setTime($time);

        $this->assertSame($time, $this->package->getTime());
    }

    public function testSetAndGetAutoload(): void
    {
        $array = [
            'psr-4' => [
                'Narrowspark\\Automatic\\Common\\Test\\' => 'tests/Common/',
                'Narrowspark\\Automatic\\Test\\'         => 'tests/Automatic/',
            ],
        ];

        $this->package->setAutoload($array);

        $this->assertSame($array, $this->package->getAutoload());
    }

    public function testToArray(): void
    {
        $array = $this->package->toArray();

        $this->assertSame(
            [
                'pretty-name'     => 'test/Test',
                'version'         => '1',
                'parent'          => null,
                'is-dev'          => false,
                'url'             => null,
                'operation'       => null,
                'type'            => null,
                'requires'        => [],
                'automatic-extra' => [],
                'autoload'        => [],
                'created'         => $this->package->getTime(),
            ],
            $array
        );
    }

    public function testCreateFromLock(): void
    {
        $lockdata = [
            'pretty-name'     => 'test/Test',
            'version'         => '1',
            'parent'          => null,
            'is-dev'          => false,
            'url'             => null,
            'operation'       => null,
            'type'            => null,
            'requires'        => [],
            'automatic-extra' => [],
            'created'         => $this->package->getTime(),
        ];

        $this->assertInstanceOf(ContractPackage::class, Package::createFromLock('test/test', $lockdata));
    }
}
