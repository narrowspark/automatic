<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Common\Test;

use Narrowspark\Discovery\Common\Package;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PackageTest extends TestCase
{
    /**
     * @var \Narrowspark\Discovery\Common\Package
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
        $this->assertSame('test', $this->package->getName());
    }

    public function testGetVersion(): void
    {
        $this->assertSame('1', $this->package->getVersion());
    }

    public function testGetPackagePath(): void
    {
        $this->assertSame(
            \str_replace('\\', '/', __DIR__ . '/test/'),
            $this->package->getPackagePath()
        );
    }

    public function testGetConfiguratorOptions(): void
    {
        $options = $this->package->getConfiguratorOptions('copy');

        $this->assertEquals(['from' => 'to'], $options);

        $options = $this->package->getConfiguratorOptions('test');

        $this->assertEquals([], $options);
    }

    public function testGetOptions(): void
    {
        $this->assertEquals($this->config, $this->package->getOptions());
    }

    public function testGetUrl(): void
    {
        $this->assertSame($this->config['url'], $this->package->getUrl());
    }

    public function testGetOperation(): void
    {
        $this->assertSame($this->config['operation'], $this->package->getOperation());
    }

    public function testGetType(): void
    {
        $this->assertSame($this->config['type'], $this->package->getType());
    }

    public function testIsExtraDependency(): void
    {
        $this->assertTrue($this->package->isExtraDependency());
    }

    public function testGetRequire(): void
    {
        $this->assertSame([], $this->package->getRequires());
    }

    public function testGetOption(): void
    {
        $this->assertSame('foo/bar', $this->package->getOption('extra-dependency-of'));
    }
}
