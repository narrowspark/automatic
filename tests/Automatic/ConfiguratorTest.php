<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\Configurator;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use ReflectionClass;

/**
 * @internal
 */
final class ConfiguratorTest extends MockeryTestCase
{
    /**
     * @var \Composer\Composer
     */
    private $composerMock;

    /**
     * @var \Composer\IO\NullIo
     */
    private $nullIo;

    /**
     * @var \Narrowspark\Automatic\Configurator
     */
    private $configurator;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composerMock = $this->mock(Composer::class);

        $this->nullIo       = new NullIO();
        $this->configurator = new Configurator($this->composerMock, $this->nullIo, []);
    }

    public function testAdd(): void
    {
        $ref = new ReflectionClass($this->configurator);
        /** @var \ReflectionProperty $property */
        $property = $ref->getProperty('configurators');
        $property->setAccessible(true);

        static::assertArrayNotHasKey('mock-configurator', $property->getValue($this->configurator));

        $mockConfigurator = $this->getMockForAbstractClass(ConfiguratorContract::class, [$this->composerMock, $this->nullIo, []]);
        $this->configurator->add('mock-configurator', \get_class($mockConfigurator));

        static::assertArrayHasKey('mock-configurator', $property->getValue($this->configurator));
    }

    public function testAddWithExistingConfiguratorName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configurator with the name "mock-configurator" already exists.');

        $mockConfigurator = $this->getMockForAbstractClass(ConfiguratorContract::class, [$this->composerMock, $this->nullIo, []]);

        $this->configurator->add('mock-configurator', \get_class($mockConfigurator));
        $this->configurator->add('mock-configurator', \get_class($mockConfigurator));
    }

    public function testAddWithoutConfiguratorContractClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configurator class "stdClass" must extend the class "Narrowspark\\Automatic\\Common\\Contract\\Configurator".');

        $this->configurator->add('foo/mock-configurator', \stdClass::class);
    }

    public function testConfigureWithCopy(): void
    {
        [$filePath, $package] = $this->arrangeCopyConfiguratorTest();

        static::assertFileExists($filePath);

        \unlink($filePath);
    }

    public function testUnconfigureWithCopy(): void
    {
        [$filePath, $package] = $this->arrangeCopyConfiguratorTest();

        static::assertFileExists($filePath);

        $this->configurator->unconfigure($package);

        static::assertFileNotExists($filePath);
    }

    /**
     * @return array
     */
    protected function arrangeCopyConfiguratorTest(): array
    {
        $this->composerMock->shouldReceive('getConfig->get')
            ->with('vendor-dir')
            ->andReturn(__DIR__);

        $toFileName = 'copy_of_copy.txt';

        $package = new Package('Fixture/stub', '1.0');
        $package->setConfig([
            'copy'      => [
                'copy.txt' => $toFileName,
            ],
        ]);

        $this->configurator->configure($package);

        $filePath = \sys_get_temp_dir() . '/' . $toFileName;

        return [$filePath, $package];
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
