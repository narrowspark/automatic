<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Narrowspark\Discovery\Package;
use PHPUnit\Framework\TestCase;

class PackageTest extends TestCase
{
    /**
     * @var \Narrowspark\Discovery\Package
     */
    private $package;

    /**
     * @var array
     */
    private $config;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'version'   => '1',
            'url'       => 'example.local',
            'type'      => 'library',
            'operation' => 'i',
            'copy'      => [
                'from' => 'to',
            ],
            'extra-dependency-of' => 'foo/bar',
            'used-by-discovery'   => true,
        ];
        $this->package = new Package('test', __DIR__, $this->config);
    }

    public function testGetName(): void
    {
        self::assertSame('test', $this->package->getName());
    }

    public function testGetVersion(): void
    {
        self::assertSame('1', $this->package->getVersion());
    }

    public function testGetPackagePath(): void
    {
        self::assertSame(
            str_replace('\\', '/', __DIR__ . '/test/'),
            $this->package->getPackagePath()
        );
    }

    public function testGetConfiguratorOptions(): void
    {
        $options = $this->package->getConfiguratorOptions('copy');

        self::assertEquals(['from' => 'to'], $options);

        $options = $this->package->getConfiguratorOptions('test');

        self::assertEquals([], $options);
    }

    public function testGetOptions(): void
    {
        self::assertEquals($this->config, $this->package->getOptions());
    }

    public function testGetUrl(): void
    {
        self::assertSame($this->config['url'], $this->package->getUrl());
    }

    public function testGetOperation(): void
    {
        self::assertSame($this->config['operation'], $this->package->getOperation());
    }

    public function testGetType(): void
    {
        self::assertSame($this->config['type'], $this->package->getType());
    }

    public function testIsExtraDependency(): void
    {
        self::assertTrue($this->package->isExtraDependency());
    }

    public function testIsDiscoveryPackage(): void
    {
        self::assertTrue($this->package->isDiscoveryPackage());
    }
}
