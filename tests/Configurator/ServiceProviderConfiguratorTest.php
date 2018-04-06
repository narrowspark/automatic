<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Configurator;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Discovery\Configurator\ServiceProviderConfigurator;
use Narrowspark\Discovery\Package;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Viserio\Component\Contract\Foundation\Kernel;

class ServiceProviderConfiguratorTest extends MockeryTestCase
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
     * @var \Narrowspark\Discovery\Configurator\ServiceProviderConfigurator
     */
    private $configurator;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new Composer();
        $this->nullIo   = new NullIO();

        $config = [
            'config-dir' => __DIR__,
        ];

        $this->configurator = new ServiceProviderConfigurator($this->composer, $this->nullIo, $config);
    }

    public function testConfigureWithGlobalProvider(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [
                            self::class,
                        ],
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);

        $filePath = __DIR__ . '/serviceproviders.php';

        self::assertFileContainsString($filePath, ServiceProviderConfigurator::getGlobalServiceProviderCommentary());

        $array = include $filePath;

        self::assertSame(self::class, $array[0]);

        \unlink($filePath);
    }

    public function testConfigureWithGlobalAndLocalProvider(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [
                            self::class,
                        ],
                        'local' => [
                            self::class,
                        ],
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);

        $kernel = $this->mock(Kernel::class);
        $kernel->shouldReceive('isLocal')
            ->andReturn(false);
        $kernel->shouldReceive('isRunningUnitTests')
            ->andReturn(true);

        $filePath = __DIR__ . '/serviceproviders.php';

        self::assertFileContainsString($filePath, ServiceProviderConfigurator::getGlobalServiceProviderCommentary());
        self::assertFileContainsString($filePath, ServiceProviderConfigurator::getLocalServiceProviderCommentary());

        $array = include $filePath;

        self::assertSame(self::class, $array[0]);
        self::assertSame(self::class, $array[1]);

        \unlink($filePath);
    }

    public function testSkipMarkedFiles(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [
                            self::class,
                        ],
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);

        $filePath = __DIR__ . '/serviceproviders.php';

        self::assertFileContainsString($filePath, ServiceProviderConfigurator::getGlobalServiceProviderCommentary());

        $array = include $filePath;

        self::assertSame(self::class, $array[0]);

        $this->configurator->configure($package);

        self::assertFalse(isset($array[1]));

        \unlink($filePath);
    }

    public function testUpdateAExistedFileWithGlobalProvider(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [
                            self::class,
                        ],
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);

        $kernel = $this->mock(Kernel::class);
        $kernel->shouldReceive('isLocal')
            ->andReturn(false);
        $kernel->shouldReceive('isRunningUnitTests')
            ->andReturn(true);

        $filePath = __DIR__ . '/serviceproviders.php';

        self::assertFileContainsString($filePath, ServiceProviderConfigurator::getGlobalServiceProviderCommentary());

        $array = include $filePath;

        self::assertSame(self::class, $array[0]);

        $package = new Package(
            'test2',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [
                            Package::class,
                        ],
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);

        $kernel = $this->mock(Kernel::class);
        $kernel->shouldReceive('isLocal')
            ->andReturn(false);
        $kernel->shouldReceive('isRunningUnitTests')
            ->andReturn(true);

        $filePath = __DIR__ . '/serviceproviders.php';

        $array = include $filePath;

        self::assertSame(self::class, $array[0]);
        self::assertSame(Package::class, $array[1]);

        \unlink($filePath);
    }

    public function testUpdateAExistedFileWithGlobalAndLocalProvider(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [
                            self::class,
                        ],
                        'local' => [
                            self::class,
                        ],
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);

        $kernel = $this->mock(Kernel::class);
        $kernel->shouldReceive('isLocal')
            ->andReturn(false);
        $kernel->shouldReceive('isRunningUnitTests')
            ->andReturn(true);

        $filePath = __DIR__ . '/serviceproviders.php';

        self::assertFileContainsString($filePath, ServiceProviderConfigurator::getGlobalServiceProviderCommentary());
        self::assertFileContainsString($filePath, ServiceProviderConfigurator::getLocalServiceProviderCommentary());

        $array = include $filePath;

        self::assertSame(self::class, $array[0]);
        self::assertSame(self::class, $array[1]);

        $package = new Package(
            'test2',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [
                            Package::class,
                        ],
                        'local' => [
                            Package::class,
                        ],
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);

        $kernel = $this->mock(Kernel::class);
        $kernel->shouldReceive('isLocal')
            ->andReturn(false);
        $kernel->shouldReceive('isRunningUnitTests')
            ->andReturn(true);

        $filePath = __DIR__ . '/serviceproviders.php';

        $array = include $filePath;

        self::assertSame(self::class, $array[0]);
        self::assertSame(Package::class, $array[1]);
        self::assertSame(self::class, $array[2]);
        self::assertSame(Package::class, $array[3]);

        \unlink($filePath);
    }

    public function testConfigureWithEmptyGlobalAndLocalProvider(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'local' => [
                            self::class,
                        ],
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);

        $kernel = $this->mock(Kernel::class);
        $kernel->shouldReceive('isLocal')
            ->andReturn(false);
        $kernel->shouldReceive('isRunningUnitTests')
            ->andReturn(true);

        $filePath = __DIR__ . '/serviceproviders.php';

        self::assertFileContainsString($filePath, ServiceProviderConfigurator::getLocalServiceProviderCommentary());

        $array = include $filePath;

        self::assertSame(self::class, $array[0]);

        \unlink($filePath);
    }

    public function testConfigureWithEmptyProvidersConfig(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);

        $filePath = __DIR__ . '/serviceproviders.php';

        self::assertFileNotExists($filePath);

        $package = new Package(
            'test',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [],
                        'local'  => [],
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);

        $filePath = __DIR__ . '/serviceproviders.php';

        self::assertFileNotExists($filePath);
    }

    public function testUnconfigureWithGlobalProviders(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [
                            self::class,
                        ],
                    ],
                ],
                Package::UNCONFIGURE => [
                    'providers' => [
                        'global' => [
                            self::class,
                        ],
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);
        $this->configurator->unconfigure($package);

        $package = new Package(
            'test2',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [
                            Package::class,
                        ],
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);

        $filePath = __DIR__ . '/serviceproviders.php';

        $array = include $filePath;

        self::assertSame(Package::class, $array[0]);
        self::assertFalse(isset($array[1]));

        \unlink($filePath);
    }

    public function testUnconfigureWithGlobalAndLocalProviders(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [
                            self::class,
                        ],
                        'local' => [
                            Package::class,
                        ],
                    ],
                ],
                Package::UNCONFIGURE => [
                    'providers' => [
                        'global' => [
                            self::class,
                        ],
                        'local' => [
                            Package::class,
                        ],
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);
        $this->configurator->unconfigure($package);

        $package = new Package(
            'test2',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [
                            Package::class,
                        ],
                    ],
                ],
            ]
        );

        $kernel = $this->mock(Kernel::class);
        $kernel->shouldReceive('isLocal')
            ->andReturn(false);
        $kernel->shouldReceive('isRunningUnitTests')
            ->andReturn(true);

        $this->configurator->configure($package);

        $filePath = __DIR__ . '/serviceproviders.php';

        $array = include $filePath;

        self::assertSame(Package::class, $array[0]);
        self::assertFalse(isset($array[1], $array[2]));

        \unlink($filePath);
    }

    public function testUnconfigureWithEmptyProvidersConfig(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [
                            Package::class,
                        ],
                    ],
                ],
                Package::UNCONFIGURE => [
                    'providers' => [
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);
        $this->configurator->unconfigure($package);

        $filePath = __DIR__ . '/serviceproviders.php';

        $array = include $filePath;

        self::assertSame(Package::class, $array[0]);

        $package = new Package(
            'test',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'providers' => [
                        'global' => [
                            Package::class,
                        ],
                    ],
                ],
                Package::UNCONFIGURE => [
                    'providers' => [
                        'global' => [],
                        'local'  => [],
                    ],
                ],
            ]
        );

        $this->configurator->configure($package);
        $this->configurator->unconfigure($package);

        $filePath = __DIR__ . '/serviceproviders.php';

        $array = include $filePath;

        self::assertSame(Package::class, $array[0]);
        self::assertSame(Package::class, $array[1]);

        \unlink($filePath);
    }

    protected static function assertFileContainsString(string $filePath, string $commentary): void
    {
        self::assertContains($commentary, \file_get_contents($filePath));
    }
}
