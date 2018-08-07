<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Test;

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
            'extraDependencyOf' => 'foo/bar',
            'used-by-automatic'   => true,
            'isDev' => false,
        ];
        $this->package = new Package('test', 'test/test', __DIR__, $this->config);
    }

    public function testGetName(): void
    {
        static::assertSame('test', $this->package->getName());
    }

    public function testGetPrettyName(): void
    {
        static::assertSame('test/test', $this->package->getPrettyName());
    }

    public function testIsDev(): void
    {
        static::assertFalse($this->package->isDev());
    }

    public function testGetPrettyVersion(): void
    {
        static::assertSame('1', $this->package->getPrettyVersion());
    }

    public function testGetPackagePath(): void
    {
        static::assertSame(
            \str_replace('\\', '/', __DIR__ . '/test/test/'),
            $this->package->getPackagePath()
        );
    }

    public function testGetConfiguratorOptions(): void
    {
        $options = $this->package->getConfiguratorOptions('copy');

        static::assertEquals(['from' => 'to'], $options);

        $options = $this->package->getConfiguratorOptions('test');

        static::assertEquals([], $options);
    }

    public function testGetOptions(): void
    {
        static::assertEquals($this->config, $this->package->getOptions());
    }

    public function testGetUrl(): void
    {
        static::assertSame($this->config['url'], $this->package->getUrl());
    }

    public function testGetOperation(): void
    {
        static::assertSame($this->config['operation'], $this->package->getOperation());
    }

    public function testGetType(): void
    {
        static::assertSame($this->config['type'], $this->package->getType());
    }

    public function testIsExtraDependency(): void
    {
        static::assertTrue($this->package->isExtraDependency());
    }

    public function testGetRequire(): void
    {
        static::assertSame([], $this->package->getRequires());
    }

    public function testGetOption(): void
    {
        static::assertSame('foo/bar', $this->package->getOption('extraDependencyOf'));
    }

    public function testToJson(): void
    {
        $json = $this->package->toJson();

        self::assertJson($json);
        self::assertSame(
            array_merge(
                ['name' => 'test', 'prettyName' => 'test/test', 'packagePath' => \str_replace('\\', '/', __DIR__ . '/test/test/')],
                $this->config
            ),
            json_decode($json, true)
        );
    }
}
