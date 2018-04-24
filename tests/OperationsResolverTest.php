<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Narrowspark\Discovery\OperationsResolver;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

class OperationsResolverTest extends MockeryTestCase
{
    /**
     * @var \Narrowspark\Discovery\OperationsResolver
     */
    private $resolver;

    /**
     * @var \Composer\DependencyResolver\Operation\InstallOperation|\Mockery\MockInterface
     */
    private $installOperation;

    /**
     * @var \Composer\DependencyResolver\Operation\UpdateOperation|\Mockery\MockInterface
     */
    private $updateOperation;

    /**
     * @var \Composer\DependencyResolver\Operation\UninstallOperation|\Mockery\MockInterface
     */
    private $uninstallOperation;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->installOperation   = $this->mock(InstallOperation::class);
        $this->updateOperation    = $this->mock(UpdateOperation::class);
        $this->uninstallOperation = $this->mock(UninstallOperation::class);

        $operations = [
            $this->installOperation,
            $this->updateOperation,
            $this->uninstallOperation,
        ];

        $this->resolver = new OperationsResolver($operations, __DIR__);
    }

    public function testResolver(): void
    {
        $package1Mock = $this->mock(PackageInterface::class);
        $package1Mock->shouldReceive('getExtra')
            ->times(3)
            ->andReturn(['discovery' =>  []]);
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
            ->andReturn([]);

        $package3Mock = $this->mock(PackageInterface::class);
        $package3Mock->shouldReceive('getExtra')
            ->times(3)
            ->andReturn(['branch-alias' => ['dev-master' => '1.0-dev'], 'discovery' =>  []]);
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
            ->times(3)
            ->andReturn('foo/bar');

        $link2Mock = $this->mock(Link::class);
        $link2Mock->shouldReceive('getTarget')
            ->times(2)
            ->andReturn('ext-mbstring');

        $package3Mock->shouldReceive('getRequires')
            ->once()
            ->andReturn([
                $link1Mock,
                $link2Mock,
            ]);

        $this->installOperation->shouldReceive('getPackage')
            ->andReturn($package1Mock);
        $this->updateOperation->shouldReceive('getTargetPackage')
            ->andReturn($package2Mock);
        $this->uninstallOperation->shouldReceive('getPackage')
            ->andReturn($package3Mock);

        $this->resolver->setParentPackageName('foo/bar');
        $packages = $this->resolver->resolve();

        $package = $packages['install'];

        self::assertSame('install', $package->getName());
        self::assertSame('1', $package->getVersion());
        self::assertSame('library', $package->getType());
        self::assertSame('example.local', $package->getUrl());
        self::assertSame('install', $package->getOperation());
        self::assertTrue($package->isExtraDependency());

        $package = $packages['uninstall'];

        self::assertSame('uninstall', $package->getName());
        self::assertSame('1.0-dev', $package->getVersion());
        self::assertSame('provider', $package->getType());
        self::assertSame('example.local', $package->getUrl());
        self::assertSame('uninstall', $package->getOperation());
        self::assertSame(['foo/bar'], $package->getRequires());
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
