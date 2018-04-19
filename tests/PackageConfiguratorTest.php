<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Narrowspark\Discovery\Package;
use Narrowspark\Discovery\PackageConfigurator;
use Narrowspark\Discovery\Test\Fixtures\MockConfigurator;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use ReflectionClass;

class PackageConfiguratorTest extends MockeryTestCase
{
    /**
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * @var \Composer\IO\NullIo
     */
    private $nullIo;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->composer = new Composer();
        $this->nullIo   = new NullIO();
    }

    public function testAddConfigurators(): void
    {
        $mockConfigurator = new MockConfigurator($this->composer, $this->nullIo, []);

        $configurator = new PackageConfigurator($this->composer, $this->nullIo, [], ['mock' => \get_class($mockConfigurator)]);

        $ref = new ReflectionClass($configurator);
        // @var \ReflectionProperty $property
        $property = $ref->getProperty('configurators');
        $property->setAccessible(true);

        self::assertArrayHasKey('mock', $property->getValue($configurator));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Configurator class "stdClass" must extend the class "Narrowspark\Discovery\Common\Contract\Configurator".
     */
    public function testAddWithoutConfiguratorContractClass(): void
    {
        new PackageConfigurator($this->composer, $this->nullIo, [], ['test' => \stdClass::class]);
    }

    public function testConfiguratorWithPackageConfigurator(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'version'              => '1',
                'url'                  => 'example.local',
                'type'                 => 'library',
                'operation'            => 'i',
                'custom-configurators' => [
                    'mock' => MockConfigurator::class,
                ],
                'mock' => [
                    'test',
                ],
            ]
        );

        $io = $this->mock(IOInterface::class);
        $io->shouldReceive('writeError')
            ->twice()
            ->with(['    test'], true, IOInterface::VERBOSE);

        $configurator = new PackageConfigurator($this->composer, $io, [], $package->getConfiguratorOptions('custom-configurators'));

        $configurator->configure($package);
        $configurator->unconfigure($package);
    }

    public function testConfiguratorOutWithPackageConfigurator(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'version'   => '1',
                'url'       => 'example.local',
                'type'      => 'library',
                'operation' => 'i',
                'mock'      => [
                    'test',
                ],
            ]
        );

        $io = $this->mock(IOInterface::class);
        $io->shouldReceive('writeError')
            ->never()
            ->with(['    test'], true, IOInterface::VERBOSE);

        $configurator = new PackageConfigurator($this->composer, $io, [], $package->getConfiguratorOptions('custom-configurators'));

        $configurator->configure($package);
        $configurator->unconfigure($package);
    }
}
