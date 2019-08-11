<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Operation;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Configurator;
use Narrowspark\Automatic\Contract\PackageConfigurator as PackageConfiguratorContract;
use Narrowspark\Automatic\Operation\Install;
use Narrowspark\Automatic\ScriptExecutor;
use Narrowspark\Automatic\Test\Fixture\Test\TransformWithScriptsExecutor\Automatic\TestExecutor;
use Narrowspark\Automatic\Test\Operation\Traits\ArrangeOperationsClasses;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 */
final class InstallTest extends MockeryTestCase
{
    use ArrangeOperationsClasses;

    /** @var \Composer\DependencyResolver\Operation\InstallOperation|\Mockery\MockInterface */
    private $installOperationMock;

    /** @var \Composer\DependencyResolver\Operation\UpdateOperation|\Mockery\MockInterface */
    private $updateOperationMock;

    /** @var \Narrowspark\Automatic\Operation\Install */
    private $install;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->installOperationMock = $this->mock(InstallOperation::class);
        $this->updateOperationMock  = $this->mock(UpdateOperation::class);

        $this->arrangeOperationsClasses();

        $this->install = new Install(
            $this->fixturePath,
            $this->lockMock,
            $this->ioMock,
            $this->configurator,
            $this->packageConfigurator,
            $this->classFinder
        );
    }

    public function testSupportsWithInstallAndExtraAutomaticKey(): void
    {
        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getExtra')
            ->once()
            ->andReturn(['automatic' => []]);
        $packageMock->shouldReceive('getName')
            ->once()
            ->andReturn('install/install');

        $this->installOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        $this->assertTrue($this->install->supports($this->installOperationMock));
    }

    public function testSupportsWithAutomaticJsonFile(): void
    {
        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getExtra')
            ->never();
        $packageMock->shouldReceive('getName')
            ->once()
            ->andReturn('narrowspark/automatic');

        $this->installOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        $this->assertTrue($this->install->supports($this->installOperationMock));
    }

    public function testSupportsWithInstallWithoutAutomatic(): void
    {
        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getExtra')
            ->once()
            ->andReturn([]);
        $packageMock->shouldReceive('getName')
            ->once()
            ->andReturn('install/install');

        $this->installOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        $this->assertFalse($this->install->supports($this->installOperationMock));
    }

    public function testSupportsWithUpdateAndExtraAutomaticKey(): void
    {
        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getExtra')
            ->once()
            ->andReturn(['automatic' => []]);
        $packageMock->shouldReceive('getName')
            ->once()
            ->andReturn('install/install');

        $this->updateOperationMock->shouldReceive('getTargetPackage')
            ->andReturn($packageMock);

        $this->assertTrue($this->install->supports($this->updateOperationMock));
    }

    public function testSupportsWithUpdateAndAutomaticJsonFile(): void
    {
        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getExtra')
            ->never();
        $packageMock->shouldReceive('getName')
            ->once()
            ->andReturn('narrowspark/automatic');

        $this->updateOperationMock->shouldReceive('getTargetPackage')
            ->andReturn($packageMock);

        $this->assertTrue($this->install->supports($this->updateOperationMock));
    }

    public function testResolveWithInstall(): void
    {
        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getExtra')
            ->twice()
            ->andReturn(['automatic' => [], 'branch-alias' => ['dev-master' => '1.0-dev']]);
        $packageMock->shouldReceive('getName')
            ->twice()
            ->andReturn('install/install');
        $packageMock->shouldReceive('getPrettyVersion')
            ->once()
            ->andReturn('dev-master');
        $packageMock->shouldReceive('getAutoload')
            ->once()
            ->andReturn([]);
        $packageMock->shouldReceive('getSourceUrl')
            ->once()
            ->andReturn('example.local');
        $packageMock->shouldReceive('getType')
            ->once()
            ->andReturn('library');
        $packageMock->shouldReceive('getRequires')
            ->once()
            ->andReturn([]);

        $this->installOperationMock->shouldReceive('getPackage')
            ->andReturn($packageMock);

        $package = $this->install->resolve($this->installOperationMock);

        $this->assertSame('install/install', $package->getName());
        $this->assertSame('1.0-dev', $package->getPrettyVersion());
        $this->assertSame('library', $package->getType());
        $this->assertSame('example.local', $package->getUrl());
        $this->assertSame('install', $package->getOperation());
    }

    public function testResolveWithUpdateAndAutomaticJsonFile(): void
    {
        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getExtra')
            ->once()
            ->andReturn([]);
        $packageMock->shouldReceive('getName')
            ->twice()
            ->andReturn('narrowspark/automatic');
        $packageMock->shouldReceive('getPrettyVersion')
            ->once()
            ->andReturn('1');
        $packageMock->shouldReceive('getAutoload')
            ->once()
            ->andReturn([]);
        $packageMock->shouldReceive('getSourceUrl')
            ->once()
            ->andReturn('example.local');
        $packageMock->shouldReceive('getType')
            ->once()
            ->andReturn('library');

        $link1Mock = $this->mock(Link::class);
        $link1Mock->shouldReceive('getTarget')
            ->once()
            ->andReturn('foo/bar');

        $link2Mock = $this->mock(Link::class);
        $link2Mock->shouldReceive('getTarget')
            ->once()
            ->andReturn('ext-mbstring');

        $packageMock->shouldReceive('getRequires')
            ->once()
            ->andReturn([
                $link1Mock,
                $link2Mock,
            ]);

        $this->updateOperationMock->shouldReceive('getTargetPackage')
            ->andReturn($packageMock);

        $package = $this->install->resolve($this->updateOperationMock);

        $this->assertSame('narrowspark/automatic', $package->getName());
        $this->assertSame('1', $package->getPrettyVersion());
        $this->assertSame('library', $package->getType());
        $this->assertSame('example.local', $package->getUrl());
        $this->assertSame('update', $package->getOperation());
        $this->assertSame(
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

    public function testTransform(): void
    {
        $packageData = [];

        [$package, $name] = $this->arrangeTransformPackage('test', [], $packageData);

        $this->lockMock->shouldReceive('addSub')
            ->once()
            ->with(Automatic::LOCK_PACKAGES, $name, $packageData);

        $this->install->transform($package);
    }

    public function testTransformWithScriptsExecutor(): void
    {
        $packageData = [];
        $autoload    = [
            'psr-4' => [
                'Narrowspark\Automatic\Test\Automatic' => '',
            ],
        ];

        $packageName = 'Test/TransformWithScriptsExecutor';

        [$package, $name] = $this->arrangeTransformPackage($packageName, $autoload, $packageData);

        $package->shouldReceive('getConfig')
            ->once()
            ->with('script-extenders')
            ->andReturn([TestExecutor::class, ScriptExecutor::class]);

        $this->ioMock->shouldReceive('write')
            ->once()
            ->with(['1 script-extender was not found in [Test/TransformWithScriptsExecutor]', '        - ' . ScriptExecutor::class], true, IOInterface::VERBOSE);

        $this->lockMock->shouldReceive('addSub')
            ->once()
            ->with(
                ScriptExecutor::TYPE,
                $name,
                [
                    TestExecutor::class => $this->fixturePath . \DIRECTORY_SEPARATOR . \str_replace('/', \DIRECTORY_SEPARATOR, $packageName) . \DIRECTORY_SEPARATOR . 'Automatic' . \DIRECTORY_SEPARATOR . 'TestExecutor.php',
                ]
            );
        $this->lockMock->shouldReceive('addSub')
            ->once()
            ->with(Automatic::LOCK_PACKAGES, $name, $packageData);

        $this->install->transform($package);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    /**
     * @param string $name
     * @param array  $autoloadData
     * @param array  $packageData
     *
     * @return array
     */
    private function arrangeTransformPackage(
        string $name        = 'test',
        array $autoloadData = [],
        array $packageData  = []
    ): array {
        $package = $this->mock(PackageContract::class);

        $package->shouldReceive('getName')
            ->twice()
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
            ->andReturn([Configurator\EnvConfigurator::class]);
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
            ->andReturn($autoloadData);

        $package->shouldReceive('toArray')
            ->once()
            ->andReturn($packageData);

        return [$package, $name];
    }
}
