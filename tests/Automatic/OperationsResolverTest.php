<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Lock;
use Narrowspark\Automatic\OperationsResolver;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 */
final class OperationsResolverTest extends MockeryTestCase
{
    /**
     * @var \Narrowspark\Automatic\OperationsResolver
     */
    private $resolver;

    /**
     * @var \Composer\DependencyResolver\Operation\InstallOperation|\Mockery\MockInterface
     */
    private $installOperationMock;

    /**
     * @var \Composer\DependencyResolver\Operation\UpdateOperation|\Mockery\MockInterface
     */
    private $updateOperationMock;

    /**
     * @var \Composer\DependencyResolver\Operation\UninstallOperation|\Mockery\MockInterface
     */
    private $uninstallOperationMock;

    /**
     * @var \Mockery\MockInterface|\Narrowspark\Automatic\Lock
     */
    private $lockMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->installOperationMock   = $this->mock(InstallOperation::class);
        $this->updateOperationMock    = $this->mock(UpdateOperation::class);
        $this->uninstallOperationMock = $this->mock(UninstallOperation::class);
        $this->lockMock               = $this->mock(Lock::class);

        $this->resolver = new OperationsResolver($this->lockMock, __DIR__ . '/Fixture');
    }

    public function testResolve(): void
    {
        $package1Mock = $this->mock(PackageInterface::class);
        $package1Mock->shouldReceive('getExtra')
            ->times(3)
            ->andReturn(['automatic' => []]);
        $package1Mock->shouldReceive('getName')
            ->twice()
            ->andReturn('install/install');
        $package1Mock->shouldReceive('getPrettyVersion')
            ->once()
            ->andReturn('1');
        $package1Mock->shouldReceive('getAutoload')
            ->once()
            ->andReturn([]);
        $package1Mock->shouldReceive('getSourceUrl')
            ->once()
            ->andReturn('example.local');
        $package1Mock->shouldReceive('getType')
            ->once()
            ->andReturn('library');
        $package1Mock->shouldReceive('getRequires')
            ->once()
            ->andReturn([]);

        $package2Mock = $this->mock(PackageInterface::class);
        $package2Mock->shouldReceive('getName')
            ->once()
            ->andReturn('install/noop');
        $package2Mock->shouldReceive('getExtra')
            ->once()
            ->andReturn([]);

        $package3Mock = $this->mock(PackageInterface::class);
        $package3Mock->shouldReceive('getExtra')
            ->times(3)
            ->andReturn(['branch-alias' => ['dev-master' => '1.0-dev'], 'automatic' => []]);
        $package3Mock->shouldReceive('getName')
            ->twice()
            ->andReturn('uninstall/uninstall');
        $package3Mock->shouldReceive('getPrettyVersion')
            ->once()
            ->andReturn('dev-master');
        $package3Mock->shouldReceive('getSourceUrl')
            ->once()
            ->andReturn('example.local');
        $package3Mock->shouldReceive('getType')
            ->once()
            ->andReturn('provider');
        $package3Mock->shouldReceive('getAutoload')
            ->once()
            ->andReturn([]);

        $link1Mock = $this->mock(Link::class);
        $link1Mock->shouldReceive('getTarget')
            ->once()
            ->andReturn('foo/bar');

        $link2Mock = $this->mock(Link::class);
        $link2Mock->shouldReceive('getTarget')
            ->once()
            ->andReturn('ext-mbstring');

        $package3Mock->shouldReceive('getRequires')
            ->once()
            ->andReturn([
                $link1Mock,
                $link2Mock,
            ]);

        $this->installOperationMock->shouldReceive('getPackage')
            ->andReturn($package1Mock);
        $this->updateOperationMock->shouldReceive('getTargetPackage')
            ->andReturn($package2Mock);
        $this->uninstallOperationMock->shouldReceive('getPackage')
            ->andReturn($package3Mock);

        $this->lockMock->shouldReceive('has')
            ->with(Automatic::LOCK_PACKAGES, 'uninstall/uninstall')
            ->andReturn(false);

        $packages = $this->resolver->resolve([
            $this->installOperationMock,
            $this->updateOperationMock,
            $this->uninstallOperationMock,
        ]);

        $package = $packages['install/install'];

        static::assertSame('install/install', $package->getName());
        static::assertSame('1', $package->getPrettyVersion());
        static::assertSame('library', $package->getType());
        static::assertSame('example.local', $package->getUrl());
        static::assertSame('install', $package->getOperation());

        $package = $packages['uninstall/uninstall'];

        static::assertSame('uninstall/uninstall', $package->getName());
        static::assertSame('uninstall/uninstall', $package->getPrettyName());
        static::assertSame('1.0-dev', $package->getPrettyVersion());
        static::assertSame('provider', $package->getType());
        static::assertSame('example.local', $package->getUrl());
        static::assertSame('uninstall', $package->getOperation());
        static::assertSame(['foo/bar'], $package->getRequires());
    }

    public function testResolveWithAutomaticJsonFile(): void
    {
        $package1Mock = $this->mock(PackageInterface::class);
        $package1Mock->shouldReceive('getExtra')
            ->once()
            ->andReturn([]);
        $package1Mock->shouldReceive('getName')
            ->twice()
            ->andReturn('narrowspark/automatic');
        $package1Mock->shouldReceive('getPrettyVersion')
            ->once()
            ->andReturn('1');
        $package1Mock->shouldReceive('getSourceUrl')
            ->once()
            ->andReturn('example.local');
        $package1Mock->shouldReceive('getType')
            ->once()
            ->andReturn('library');
        $package1Mock->shouldReceive('getRequires')
            ->once()
            ->andReturn([]);
        $package1Mock->shouldReceive('getAutoload')
            ->once()
            ->andReturn([]);

        $this->installOperationMock->shouldReceive('getPackage')
            ->andReturn($package1Mock);

        $packages = $this->resolver->resolve([$this->installOperationMock]);

        $package = $packages['narrowspark/automatic'];

        static::assertSame('narrowspark/automatic', $package->getName());
        static::assertSame('1', $package->getPrettyVersion());
        static::assertSame('library', $package->getType());
        static::assertSame('example.local', $package->getUrl());
        static::assertSame('install', $package->getOperation());
        static::assertSame(
            [
                'providers' => [
                    'Viserio\\Component\\Console\\Provider\\ConsoleServiceProvider' => [
                        'global',
                    ],
                ],
            ],
            $package->getConfigs()
        );
    }

    public function testResolveWithRemoveAndLock(): void
    {
        $name = 'uninstall/uninstall';

        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getExtra')
            ->once()
            ->andReturn(['automatic' => []]);
        $packageMock->shouldReceive('getName')
            ->once()
            ->andReturn($name);

        $this->uninstallOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        $this->lockMock->shouldReceive('has')
            ->with(Automatic::LOCK_PACKAGES, $name)
            ->andReturn(true);
        $this->lockMock->shouldReceive('get')
            ->once()
            ->with(Automatic::LOCK_PACKAGES, $name)
            ->andReturn([
                'pretty-name' => $name,
                'version'     => '1.0-dev',
                'parent'      => null,
                'is-dev'      => false,
                'url'         => null,
                'operation'   => 'install',
                'type'        => 'library',
                'requires'    => [
                    'viserio/contract',
                ],
                'autoload' => [],
                'automatic-extra' => [
                    'providers' => [
                        'Viserio\\Component\\OptionsResolver\\Provider\\ConsoleCommandsServiceProvider' => [
                            'local',
                        ],
                    ],
                ],
                'created' => '2018-08-11T13:18:33+02:00',
            ]);

        $packages = $this->resolver->resolve([$this->uninstallOperationMock]);

        $package = $packages['uninstall/uninstall'];

        static::assertSame('uninstall/uninstall', $package->getName());
        static::assertSame('uninstall/uninstall', $package->getPrettyName());
        static::assertSame('1.0-dev', $package->getPrettyVersion());
        static::assertSame('library', $package->getType());
        static::assertNull($package->getUrl());
        static::assertSame('uninstall', $package->getOperation());
        static::assertSame(['viserio/contract'], $package->getRequires());
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
