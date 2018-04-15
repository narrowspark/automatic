<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Configurator;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Discovery\Common\Traits\PhpFileMarkerTrait;
use Narrowspark\Discovery\Configurator\ProxyConfigurator;
use Narrowspark\Discovery\Package;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

class ProxyConfiguratorTest extends MockeryTestCase
{
    use PhpFileMarkerTrait;

    /**
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * @var \Composer\IO\NullIo
     */
    private $nullIo;

    /**
     * @var \Narrowspark\Discovery\Configurator\ProxyConfigurator
     */
    private $configurator;

    /**
     * @var string
     */
    private $globalPath;

    /**
     * @var string
     */
    private $localPath;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new Composer();
        $this->nullIo   = new NullIO();

        $dir = __DIR__ . '/ProxyConfiguratorTest';

        $this->globalPath = $dir . '/staticalproxy.php';
        $this->localPath  = $dir . '/local/staticalproxy.php';

        $this->configurator = new ProxyConfigurator($this->composer, $this->nullIo, ['config-dir' => $dir]);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        if (\is_file($this->globalPath)) {
            \unlink($this->globalPath);
        }

        if (\is_file($this->localPath)) {
            \unlink($this->localPath);
            \rmdir(\dirname($this->localPath));
        }

        @\rmdir(__DIR__ . '/ProxyConfiguratorTest');
    }

    public function testConfigureWithGlobalProxy(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'version'   => '1',
                'proxies'   => [
                    self::class => ['global'],
                ],
            ]
        );

        $this->configurator->configure($package);

        self::assertTrue($this->isFileMarked('test', $this->globalPath));

        $array = include $this->globalPath;

        self::assertSame(self::class, $array['viserio']['staticalproxy']['aliases']['ProxyConfiguratorTest']);
    }

    public function testConfigureWithGlobalAndLocalProxy(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'version'   => '1',
                'proxies'   => [
                    self::class => ['global', 'local'],
                ],
            ]
        );

        $this->configurator->configure($package);

        self::assertTrue($this->isFileMarked('test', $this->globalPath));
        self::assertTrue($this->isFileMarked('test', $this->localPath));

        $array = include $this->globalPath;

        self::assertSame(self::class, \reset($array['viserio']['staticalproxy']['aliases']));

        $array = include $this->localPath;

        self::assertSame(self::class, \reset($array['viserio']['staticalproxy']['aliases']));
    }

    public function testSkipMarkedFiles(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'version'   => '1',
                'proxies'   => [
                    self::class => ['global'],
                ],
            ]
        );

        $this->configurator->configure($package);

        $array = include $this->globalPath;

        self::assertSame(self::class, \reset($array['viserio']['staticalproxy']['aliases']));

        $this->configurator->configure($package);

        self::assertCount(1, $array['viserio']['staticalproxy']['aliases']);
    }

    public function testUpdateExistedFileWithGlobalProxy(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'version'   => '1',
                'proxies'   => [
                    self::class => ['global'],
                ],
            ]
        );

        $this->configurator->configure($package);

        self::assertTrue($this->isFileMarked('test', $this->globalPath));

        $array = include $this->globalPath;

        self::assertSame(self::class, $array['viserio']['staticalproxy']['aliases']['ProxyConfiguratorTest']);

        $package = new Package(
            'test2',
            __DIR__,
            [
                'version'   => '1',
                'proxies'   => [
                    Package::class => ['global'],
                ],
            ]
        );

        $this->configurator->configure($package);

        self::assertTrue($this->isFileMarked('test2', $this->globalPath));

        $array = include $this->globalPath;

        self::assertSame(self::class, $array['viserio']['staticalproxy']['aliases']['ProxyConfiguratorTest']);
        self::assertSame(Package::class, $array['viserio']['staticalproxy']['aliases']['Package']);
    }

    public function testUpdateAExistedFileWithGlobalAndLocalProxy(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'version'   => '1',
                'proxies'   => [
                    self::class => ['global', 'local'],
                ],
            ]
        );

        $this->configurator->configure($package);

        $array = include $this->globalPath;

        self::assertSame(self::class, \reset($array['viserio']['staticalproxy']['aliases']));

        $array = include $this->localPath;

        self::assertSame(self::class, \reset($array['viserio']['staticalproxy']['aliases']));

        $package = new Package(
            'test2',
            __DIR__,
            [
                'version'   => '1',
                'proxies'   => [
                    Package::class => ['global', 'local'],
                ],
            ]
        );

        $this->configurator->configure($package);

        $array = include $this->globalPath;

        self::assertSame(self::class, \reset($array['viserio']['staticalproxy']['aliases']));
        self::assertSame(Package::class, \end($array['viserio']['staticalproxy']['aliases']));

        $array = include $this->localPath;

        self::assertSame(self::class, \reset($array['viserio']['staticalproxy']['aliases']));
        self::assertSame(Package::class, \end($array['viserio']['staticalproxy']['aliases']));
    }

    public function testConfigureWithEmptyProxiesConfig(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'version'   => '1',
                'proxies'   => [
                ],
            ]
        );

        $this->configurator->configure($package);

        self::assertFileNotExists($this->globalPath);
    }

    public function testUnconfigureWithGlobalProxies(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'version'   => '1',
                'proxies'   => [
                    self::class => ['global'],
                ],
            ]
        );

        $this->configurator->configure($package);

        self::assertTrue($this->isFileMarked('test', $this->globalPath));

        $this->configurator->unconfigure($package);

        self::assertFalse($this->isFileMarked('test', $this->globalPath));

        $package = new Package(
            'test2',
            __DIR__,
            [
                'version'   => '1',
                'proxies'   => [
                    Package::class => ['global'],
                ],
            ]
        );

        $this->configurator->configure($package);

        self::assertTrue($this->isFileMarked('test2', $this->globalPath));

        $array = include $this->globalPath;

        self::assertSame(Package::class, \reset($array['viserio']['staticalproxy']['aliases']));
        self::assertFalse(isset($array['viserio']['staticalproxy']['aliases']['ProxyConfiguratorTest']));
    }

    public function testUnconfigureAndConfigureAgain(): void
    {
        $package = new Package(
            'test',
            __DIR__,
            [
                'version'   => '1',
                'proxies'   => [
                    self::class    => ['global'],
                    Package::class => ['local'],
                ],
            ]
        );

        $this->configurator->configure($package);
        $this->configurator->unconfigure($package);

        $this->configurator->configure($package);

        $array = include $this->globalPath;

        self::assertCount(1, $array['viserio']['staticalproxy']['aliases']);
    }
}
