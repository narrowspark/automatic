<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Util\RemoteFilesystem;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\ClassFinder;
use Narrowspark\Automatic\Configurator;
use Narrowspark\Automatic\Container;
use Narrowspark\Automatic\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\Contract\PackageConfigurator as PackageConfiguratorContract;
use Narrowspark\Automatic\Installer\ConfiguratorInstaller;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\LegacyTagsManager;
use Narrowspark\Automatic\Lock;
use Narrowspark\Automatic\Operation\Install;
use Narrowspark\Automatic\Operation\Uninstall;
use Narrowspark\Automatic\PackageConfigurator;
use Narrowspark\Automatic\Prefetcher\ParallelDownloader;
use Narrowspark\Automatic\Prefetcher\Prefetcher;
use Narrowspark\Automatic\ScriptExecutor;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
final class ContainerTest extends MockeryTestCase
{
    /**
     * @var \Narrowspark\Automatic\Container
     */
    private static $staticContainer;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $composer   = new Composer();
        $configMock = \Mockery::mock(Config::class);
        $configMock->shouldReceive('get')
            ->with('vendor-dir')
            ->andReturn('/vendor');
        $configMock->shouldReceive('get')
            ->with('cache-files-dir')
            ->andReturn('');
        $configMock->shouldReceive('get')
            ->with('disable-tls')
            ->andReturn(true);
        $configMock->shouldReceive('get')
            ->with('bin-dir')
            ->andReturn(__DIR__);
        $configMock->shouldReceive('get')
            ->with('bin-compat')
            ->andReturn(__DIR__);

        $composer->setConfig($configMock);

        $package = \Mockery::mock(RootPackageInterface::class);
        $package->shouldReceive('getExtra')
            ->andReturn([]);

        $composer->setPackage($package);

        $eventDispatcherMock = \Mockery::mock(EventDispatcher::class);
        $eventDispatcherMock->shouldReceive('addSubscriber');

        $composer->setEventDispatcher($eventDispatcherMock);

        $downloadManager = \Mockery::mock(DownloadManager::class);
        $downloadManager->shouldReceive('getDownloader')
            ->with('file');

        $composer->setDownloadManager($downloadManager);

        self::$staticContainer = new Container($composer, new BufferIO());
    }

    /**
     * @dataProvider instancesProvider
     *
     * @param string $key
     * @param mixed  $expected
     *
     * @return void
     */
    public function testContainerInstances(string $key, $expected): void
    {
        $value = self::$staticContainer->get($key);

        if (\is_string($value) || \is_array($value)) {
            $this->assertSame($expected, $value);
        } else {
            $this->assertInstanceOf($expected, $value);
        }
    }

    /**
     * @return array
     */
    public function instancesProvider(): array
    {
        return [
            [Composer::class, Composer::class],
            [Config::class, Config::class],
            [IOInterface::class, BufferIO::class],
            ['vendor-dir', '/vendor'],
            [
                'composer-extra',
                [
                    Automatic::COMPOSER_EXTRA_KEY => [
                        'allow-auto-install' => false,
                        'dont-discover'      => [],
                    ],
                ],
            ],
            [InputInterface::class, InputInterface::class],
            [Lock::class, Lock::class],
            [ClassFinder::class, ClassFinder::class],
            [ConfiguratorInstaller::class, ConfiguratorInstaller::class],
            [SkeletonInstaller::class, SkeletonInstaller::class],
            [ConfiguratorContract::class, Configurator::class],
            [Install::class, Install::class],
            [Uninstall::class, Uninstall::class],
            [RemoteFilesystem::class, RemoteFilesystem::class],
            [ParallelDownloader::class, ParallelDownloader::class],
            [Prefetcher::class, Prefetcher::class],
            [ScriptExecutor::class, ScriptExecutor::class],
            [PackageConfiguratorContract::class, PackageConfigurator::class],
            [LegacyTagsManager::class, LegacyTagsManager::class],
        ];
    }

    public function testGetThrowException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Identifier [test] is not defined.');

        static::$staticContainer->get('test');
    }

    public function testGetCache(): void
    {
        $this->assertSame('/vendor', static::$staticContainer->get('vendor-dir'));

        static::$staticContainer->set('vendor-dir', static function () {
            return 'test';
        });

        $this->assertNotSame('test', static::$staticContainer->get('vendor-dir'));
    }

    public function testGetAll(): void
    {
        $this->assertCount(19, static::$staticContainer->getAll());
    }
}
