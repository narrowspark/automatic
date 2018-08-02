<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
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

        $this->resolver = new OperationsResolver($this->lockMock, __DIR__);
    }

    public function testResolve(): void
    {
        $package1Mock = $this->mock(PackageInterface::class);
        $package1Mock->shouldReceive('getExtra')
            ->times(3)
            ->andReturn(['automatic' =>  []]);
        $package1Mock->shouldReceive('getName')
            ->once()
            ->andReturn('install');
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

        $package2Mock = $this->mock(PackageInterface::class);
        $package2Mock->shouldReceive('getExtra')
            ->once()
            ->andReturn([]);

        $package3Mock = $this->mock(PackageInterface::class);
        $package3Mock->shouldReceive('getExtra')
            ->times(3)
            ->andReturn(['branch-alias' => ['dev-master' => '1.0-dev'], 'automatic' =>  []]);
        $package3Mock->shouldReceive('getName')
            ->once()
            ->andReturn('uninstall');
        $package3Mock->shouldReceive('getPrettyVersion')
            ->once()
            ->andReturn('dev-master');
        $package3Mock->shouldReceive('getSourceUrl')
            ->once()
            ->andReturn('example.local');
        $package3Mock->shouldReceive('getType')
            ->once()
            ->andReturn('provider');

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
            ->with('uninstall')
            ->andReturn(false);

        $this->resolver->setParentPackageName('foo/bar');

        $packages = $this->resolver->resolve([
            $this->installOperationMock,
            $this->updateOperationMock,
            $this->uninstallOperationMock,
        ]);

        $package = $packages['install'];

        static::assertSame('install', $package->getName());
        static::assertSame('1', $package->getVersion());
        static::assertSame('library', $package->getType());
        static::assertSame('example.local', $package->getUrl());
        static::assertSame('install', $package->getOperation());
        static::assertTrue($package->isExtraDependency());

        $package = $packages['uninstall'];

        static::assertSame('uninstall', $package->getName());
        static::assertSame('1.0-dev', $package->getVersion());
        static::assertSame('provider', $package->getType());
        static::assertSame('example.local', $package->getUrl());
        static::assertSame('uninstall', $package->getOperation());
        static::assertSame(['foo/bar'], $package->getRequires());
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
