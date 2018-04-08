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
            'version'  => '1',
            'copy'     => [
                'from' => 'to',
            ],
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
            \strtr(__DIR__ . '/test/', '\\', '/'),
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

    public function testGetExtraOptions(): void
    {
        $config = $this->config;

        unset($config['version']);

        self::assertEquals($config, $this->package->getExtraOptions());
    }
}
