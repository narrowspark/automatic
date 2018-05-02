<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Discovery\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Discovery\Common\Package;
use Narrowspark\Discovery\Configurator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ConfiguratorTest extends TestCase
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
     * @var \Narrowspark\Discovery\Configurator
     */
    private $configurator;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composer     = new Composer();
        $this->nullIo       = new NullIO();
        $this->configurator = new Configurator($this->composer, $this->nullIo, []);
    }

    public function testAdd(): void
    {
        $ref = new ReflectionClass($this->configurator);
        // @var \ReflectionProperty $property
        $property = $ref->getProperty('configurators');
        $property->setAccessible(true);

        self::assertArrayNotHasKey('mock-configurator', $property->getValue($this->configurator));

        $mockConfigurator = $this->getMockForAbstractClass(ConfiguratorContract::class, [$this->composer, $this->nullIo, []]);
        $this->configurator->add('mock-configurator', \get_class($mockConfigurator));

        self::assertArrayHasKey('mock-configurator', $property->getValue($this->configurator));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Configurator with the name "mock-configurator" already exists.
     */
    public function testAddWithExistingConfiguratorName(): void
    {
        $mockConfigurator = $this->getMockForAbstractClass(ConfiguratorContract::class, [$this->composer, $this->nullIo, []]);

        $this->configurator->add('mock-configurator', \get_class($mockConfigurator));
        $this->configurator->add('mock-configurator', \get_class($mockConfigurator));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Configurator class "stdClass" must extend the class "Narrowspark\Discovery\Common\Contract\Configurator".
     */
    public function testAddWithoutConfiguratorContractClass(): void
    {
        $this->configurator->add('foo/mock-configurator', \stdClass::class);
    }

    public function testConfigureWithCopy(): void
    {
        [$filePath, $package] = $this->arrangeCopyConfiguratorTest();

        self::assertFileExists($filePath);

        \unlink($filePath);
    }

    public function testUnconfigureWithCopy(): void
    {
        [$filePath, $package] = $this->arrangeCopyConfiguratorTest();

        self::assertFileExists($filePath);

        $this->configurator->unconfigure($package);

        self::assertFileNotExists($filePath);
    }

    /**
     * @return array
     */
    protected function arrangeCopyConfiguratorTest(): array
    {
        $toFileName = 'copy_of_copy.txt';

        $package = new Package(
            'Fixtures',
            __DIR__,
            [
                'version'   => '1',
                'url'       => 'example.local',
                'type'      => 'library',
                'operation' => 'i',
                'copy'      => [
                    'copy.txt' => $toFileName,
                ],
            ]
        );

        $this->configurator->configure($package);

        $filePath = \sys_get_temp_dir() . '/' . $toFileName;

        return [$filePath, $package];
    }
}
