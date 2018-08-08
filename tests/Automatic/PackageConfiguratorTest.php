<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\PackageConfigurator;
use Narrowspark\Automatic\Test\Fixtures\MockConfigurator;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use ReflectionClass;

/**
 * @internal
 */
final class PackageConfiguratorTest extends MockeryTestCase
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
     * @var \Composer\IO\IOInterface|\Mockery\MockInterface
     */
    private $ioMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new Composer();
        $this->nullIo   = new NullIO();
        $this->ioMock   = $this->mock(IOInterface::class);
    }

    public function testAddConfigurators(): void
    {
        $mockConfigurator = new MockConfigurator($this->composer, $this->nullIo, []);

        $configurator = new PackageConfigurator($this->composer, $this->nullIo, []);
        $configurator->add('mock', \get_class($mockConfigurator));

        $ref = new ReflectionClass($configurator);
        /** @var \ReflectionProperty $property */
        $property = $ref->getProperty('configurators');
        $property->setAccessible(true);

        static::assertArrayHasKey('mock', $property->getValue($configurator));
    }

    public function testAddWithoutConfiguratorContractClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configurator class [stdClass] must extend the class [Narrowspark\\Automatic\\Common\\Contract\\Configurator].');

        $configurator = new PackageConfigurator($this->composer, $this->nullIo, []);
        $configurator->add('test', \stdClass::class);
    }

    public function testConfiguratorWithPackageConfigurator(): void
    {
        $package = $this->arrangePackageWithConfig('test/test', [
            'custom-configurators' => [
                'mock' => MockConfigurator::class,
            ],
            'mock' => [
                'test'
            ]
        ]);

        $this->ioMock->shouldReceive('writeError')
            ->twice()
            ->with(['    test'], true, IOInterface::VERBOSE);

        $configurator = new PackageConfigurator($this->composer, $this->ioMock, []);
        $configurator->add('mock', MockConfigurator::class);

        $configurator->configure($package);
        $configurator->unconfigure($package);
    }

    public function testConfiguratorOutWithPackageConfigurator(): void
    {
        $package = $this->arrangePackageWithConfig('test/test', [
            'mock' => [
                'test'
            ]
        ]);

        $this->ioMock->shouldReceive('writeError')
            ->never()
            ->with(['    test'], true, IOInterface::VERBOSE);

        $configurator = new PackageConfigurator($this->composer, $this->ioMock, []);

        $configurator->configure($package);
        $configurator->unconfigure($package);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    /**
     * @param string $name
     * @param array  $config
     *
     * @throws \Exception
     *
     * @return Package
     */
    private function arrangePackageWithConfig(string $name, array $config): Package
    {
        $package = new Package($name, '1.0.0');
        $package->setConfig($config);

        return $package;
    }
}
