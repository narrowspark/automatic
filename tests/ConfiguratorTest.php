<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Discovery\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Discovery\Configurator;
use Narrowspark\Discovery\Package;
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
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new Composer();
        $this->nullIo   = new NullIO();
    }

    public function testAdd(): void
    {
        $configurator = new Configurator($this->composer, $this->nullIo, []);

        $ref = new ReflectionClass($configurator);
        // @var \ReflectionProperty $property
        $property = $ref->getProperty('configurators');
        $property->setAccessible(true);

        self::assertArrayNotHasKey('mock-configurator', $property->getValue($configurator));

        $mockConfigurator = $this->getMockForAbstractClass(ConfiguratorContract::class, [$this->composer, $this->nullIo, []]);
        $configurator->add('mock-configurator', get_class($mockConfigurator));

        self::assertArrayHasKey('mock-configurator', $property->getValue($configurator));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Configurator with the name "mock-configurator" already exists.
     */
    public function testAddWithExistingConfiguratorName(): void
    {
        $configurator = new Configurator($this->composer, $this->nullIo, []);

        $mockConfigurator = $this->getMockForAbstractClass(ConfiguratorContract::class, [$this->composer, $this->nullIo, []]);
        $configurator->add('mock-configurator', get_class($mockConfigurator));
        $configurator->add('mock-configurator', get_class($mockConfigurator));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Configurator class "stdClass" must extend the class "Narrowspark\Discovery\Common\Contract\Configurator".
     */
    public function testAddWithoutConfiguratorContractClass(): void
    {
        $configurator = new Configurator($this->composer, $this->nullIo, []);

        $configurator->add('foo/mock-configurator', \stdClass::class);
    }

    public function testConfigureWithProviders(): void
    {
        $configurator = new Configurator($this->composer, $this->nullIo, ['config-dir' => __DIR__]);

        $package = new Package(
            'test',
            __DIR__,
            [
                'version'   => '1',
                'providers' => [
                    self::class => ['global'],
                ],
            ]
        );

        $configurator->configure($package);

        $filePath = __DIR__ . '/serviceproviders.php';

        $array = include $filePath;

        self::assertSame(self::class, $array[0]);

        \unlink($filePath);
    }

    public function testConfigureWithCopy(): void
    {
        $configurator = new Configurator($this->composer, $this->nullIo, []);

        $toFileName = 'copy_of_copy.txt';

        $package = new Package(
            'Fixtures',
            __DIR__,
            [
                'version'  => '1',
                'copy'     => [
                    'copy.txt' => $toFileName,
                ],
            ]
        );

        $configurator->configure($package);

        $filePath = \sys_get_temp_dir() . '/' . $toFileName;

        self::assertFileExists($filePath);

        \unlink($filePath);
    }

    public function testUnconfigureWithProviders(): void
    {
        $configurator = new Configurator($this->composer, $this->nullIo, ['config-dir' => __DIR__]);

        $package = new Package(
            'test',
            __DIR__,
            [
                'version'   => '1',
                'providers' => [
                    self::class => ['global'],
                ],
            ]
        );

        $configurator->configure($package);
        $configurator->unconfigure($package);

        $filePath = __DIR__ . '/serviceproviders.php';

        $array = include $filePath;

        self::assertFalse(isset($array[0]));

        \unlink($filePath);
    }
}
