<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018-2020 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/automatic
 */

namespace Narrowspark\Automatic\Security\Test;

use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Util\RemoteFilesystem;
use Mockery;
use Narrowspark\Automatic\Common\Downloader\Downloader;
use Narrowspark\Automatic\Common\Downloader\ParallelDownloader;
use Narrowspark\Automatic\Security\Audit;
use Narrowspark\Automatic\Security\Container;
use Narrowspark\Automatic\Security\Contract\Audit as AuditContract;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Security\Container
 *
 * @medium
 */
final class ContainerTest extends MockeryTestCase
{
    /** @var \Narrowspark\Automatic\Security\Container */
    private $container;

    /** @var \Composer\IO\IOInterface|\Mockery\MockInterface */
    private $ioMock;

    /** @var \Composer\Config|\Mockery\MockInterface */
    private $configMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $composer = new Composer();

        $this->configMock = Mockery::mock(Config::class);
        $this->configMock->shouldReceive('get')
            ->with('vendor-dir')
            ->andReturn('/vendor');
        $this->configMock->shouldReceive('get')
            ->with('cache-files-dir')
            ->andReturn('');
        $this->configMock->shouldReceive('get')
            ->with('disable-tls')
            ->andReturn(true);
        $this->configMock->shouldReceive('get')
            ->with('bin-dir')
            ->andReturn(__DIR__);
        $this->configMock->shouldReceive('get')
            ->with('bin-compat')
            ->andReturn(__DIR__);
        $this->configMock->shouldReceive('get')
            ->with('cafile')
            ->andReturn('');
        $this->configMock->shouldReceive('get')
            ->with('capath')
            ->andReturn('');
        $this->configMock->shouldReceive('get')
            ->with('cache-repo-dir')
            ->andReturn('');

        $composer->setConfig($this->configMock);

        /** @var \Composer\Package\RootPackageInterface|\Mockery\MockInterface $package */
        $package = Mockery::mock(RootPackageInterface::class);
        $package->shouldReceive('getExtra')
            ->andReturn([]);

        $composer->setPackage($package);

        /** @var \Composer\Downloader\DownloadManager|\Mockery\MockInterface $downloadManager */
        $downloadManager = Mockery::mock(DownloadManager::class);
        $downloadManager->shouldReceive('getDownloader')
            ->with('file');

        $composer->setDownloadManager($downloadManager);

        $this->ioMock = Mockery::mock(IOInterface::class);
        $this->ioMock->shouldReceive('writeError')
            ->with('<warning>You are running Composer with SSL/TLS protection disabled.</warning>');

        if (\PHP_OS_FAMILY !== 'Windows') {
            $this->ioMock->shouldReceive('writeError')
                ->with('<warning>Cannot create cache directory /https---automatic.narrowspark.com/, or directory is not writable. Proceeding without cache</warning>');
        }

        $this->container = new Container($composer, $this->ioMock);
    }

    /**
     * @dataProvider provideContainerInstancesCases
     *
     * @param class-string<object>|mixed[] $expected
     */
    public function testContainerInstances(string $key, $expected): void
    {
        $value = $this->container->get($key);

        if (\is_string($value) || (\is_array($value) && \is_array($expected))) {
            self::assertSame($expected, $value);
        }

        if (\is_object($value) && \is_string($expected)) {
            self::assertInstanceOf($expected, $value);
        }
    }

    /**
     * @group network
     */
    public function testSecurityAdvisories(): void
    {
        $this->configMock->shouldReceive('get')
            ->with('gitlab-domains')
            ->andReturn(['gitlab.com']);

        $this->configMock->shouldReceive('prohibitUrlByConfig')
            ->with('https://automatic.narrowspark.com/security-advisories.json', Mockery::type(IOInterface::class));

        $this->ioMock->shouldReceive('writeError')
            ->with('Downloading the Security Advisories database...', true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->with('Reading /https---automatic.narrowspark.com/security-advisories.json from cache', true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('hasAuthentication')
            ->andReturn(false);
        $this->ioMock->shouldReceive('writeError')
            ->with('Retrying download: ', true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('writeError')
            ->with('Downloading https://automatic.narrowspark.com/security-advisories.json', true, IOInterface::DEBUG);

        $this->ioMock->shouldReceive('writeError')
            ->with(\sprintf('Writing %shttps---automatic.narrowspark.com/security-advisories.json into cache', \PHP_OS_FAMILY === 'Windows' ? '\\' : '/'), true, IOInterface::DEBUG);

        self::assertNotCount(0, $this->container->get('security_advisories'));
    }

    /**
     * @return array<int, array<int|string, mixed>|string>
     */
    public static function provideContainerInstancesCases(): iterable
    {
        return [
            [Composer::class, Composer::class],
            [Config::class, Config::class],
            [IOInterface::class, IOInterface::class],
            [RemoteFilesystem::class, RemoteFilesystem::class],
            ['composer-extra', []],
            [ParallelDownloader::class, ParallelDownloader::class],
            [Downloader::class, Downloader::class],
            [AuditContract::class, Audit::class],
        ];
    }

    public function testGetAll(): void
    {
        self::assertCount(10, $this->container->getAll());
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods(bool $allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
