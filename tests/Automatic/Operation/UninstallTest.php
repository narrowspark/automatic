<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\Automatic\Test\Operation;

use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Package\PackageInterface;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Configurator;
use Narrowspark\Automatic\Contract\PackageConfigurator as PackageConfiguratorContract;
use Narrowspark\Automatic\Operation\Uninstall;
use Narrowspark\Automatic\ScriptExecutor;
use Narrowspark\Automatic\Test\Operation\Traits\ArrangeOperationsClasses;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 *
 * @small
 */
final class UninstallTest extends MockeryTestCase
{
    use ArrangeOperationsClasses;

    /** @var \Composer\DependencyResolver\Operation\UninstallOperation|\Mockery\MockInterface */
    private $uninstallOperationMock;

    /** @var \Narrowspark\Automatic\Operation\Install */
    private $uninstall;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->uninstallOperationMock = $this->mock(UninstallOperation::class);

        $this->arrangeOperationsClasses();

        $this->uninstall = new Uninstall(
            $this->fixturePath,
            $this->lockMock,
            $this->ioMock,
            $this->configurator,
            $this->packageConfigurator,
            $this->classFinder
        );
    }

    public function testSupportsWithOutLock(): void
    {
        $name = 'uninstall/uninstall';

        $this->lockMock->shouldReceive('has')
            ->with(Automatic::LOCK_PACKAGES, $name)
            ->andReturn(false);

        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getName')
            ->once()
            ->andReturn($name);

        $this->uninstallOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        self::assertFalse($this->uninstall->supports($this->uninstallOperationMock));
    }

    public function testSupportsWithLock(): void
    {
        $name = 'uninstall/uninstall';

        $this->lockMock->shouldReceive('has')
            ->with(Automatic::LOCK_PACKAGES, $name)
            ->andReturn(true);

        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getName')
            ->once()
            ->andReturn($name);

        $this->uninstallOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        self::assertTrue($this->uninstall->supports($this->uninstallOperationMock));
    }

    public function testResolve(): void
    {
        $name = 'uninstall/uninstall';

        $packageMock = $this->mock(PackageInterface::class);
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
                'version' => '1.0-dev',
                'parent' => null,
                'is-dev' => false,
                'url' => null,
                'operation' => 'install',
                'type' => 'library',
                'requires' => [
                    'viserio/contract' => 'dev-master',
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

        $package = $this->uninstall->resolve($this->uninstallOperationMock);

        self::assertSame($name, $package->getName());
        self::assertSame($name, $package->getPrettyName());
        self::assertSame('1.0-dev', $package->getPrettyVersion());
        self::assertSame('library', $package->getType());
        self::assertNull($package->getUrl());
        self::assertSame('uninstall', $package->getOperation());
        self::assertSame(['viserio/contract' => 'dev-master'], $package->getRequires());
    }

    public function testTransform(): void
    {
        $package = $this->mock(PackageContract::class);

        $name = 'test';

        $package->shouldReceive('getName')
            ->once()
            ->andReturn($name);
        $package->shouldReceive('hasConfig')
            ->once()
            ->with('configurators', Configurator\ComposerAutoScriptsConfigurator::getName())
            ->andReturn(false);
        $package->shouldReceive('hasConfig')
            ->once()
            ->with('configurators', Configurator\ComposerScriptsConfigurator::getName())
            ->andReturn(false);
        $package->shouldReceive('hasConfig')
            ->once()
            ->with('configurators', Configurator\CopyFromPackageConfigurator::getName())
            ->andReturn(false);
        $package->shouldReceive('hasConfig')
            ->twice()
            ->with('configurators', Configurator\EnvConfigurator::getName())
            ->andReturn(false);
        $package->shouldReceive('hasConfig')
            ->once()
            ->with('configurators', Configurator\GitIgnoreConfigurator::getName())
            ->andReturn(false);
        $package->shouldReceive('hasConfig')
            ->once()
            ->with(PackageConfiguratorContract::TYPE)
            ->andReturn(true);
        $package->shouldReceive('getConfig')
            ->once()
            ->with(PackageConfiguratorContract::TYPE)
            ->andReturn(['test' => Configurator\EnvConfigurator::class]);
        $package->shouldReceive('getConfig')
            ->once()
            ->with(ConfiguratorContract::TYPE)
            ->andReturn([]);
        $package->shouldReceive('hasConfig')
            ->once()
            ->with(ScriptExecutor::TYPE)
            ->andReturn(true);
        $package->shouldReceive('getAutoload')
            ->once()
            ->andReturn([]);

        $this->lockMock->shouldReceive('remove')
            ->once()
            ->with(ScriptExecutor::TYPE, $name);
        $this->lockMock->shouldReceive('remove')
            ->once()
            ->with(Automatic::LOCK_PACKAGES, $name);

        $this->uninstall->transform($package);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
