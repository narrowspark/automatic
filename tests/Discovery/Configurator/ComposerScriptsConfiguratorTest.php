<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Configurator;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Narrowspark\Discovery\Common\Contract\Package as PackageContract;
use Narrowspark\Discovery\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Discovery\Configurator\ComposerScriptsConfigurator;
use Narrowspark\Discovery\Test\Fixtures\ComposerJsonFactory;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 */
final class ComposerScriptsConfiguratorTest extends MockeryTestCase
{
    use GetGenericPropertyReaderTrait;

    /**
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * @var \Composer\IO\IOInterface|\Mockery\MockInterface
     */
    private $ioMock;

    /**
     * @var \Composer\Json\JsonFile|\Mockery\MockInterface
     */
    private $jsonMock;

    /**
     * @var \Composer\Json\JsonManipulator|\Mockery\MockInterface
     */
    private $jsonManipulatorMock;

    /**
     * @var \Narrowspark\Discovery\Configurator\ComposerScriptsConfigurator
     */
    private $configurator;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonMock            = $this->mock(JsonFile::class);
        $this->jsonManipulatorMock = $this->mock(JsonManipulator::class);

        $this->composer = new Composer();
        $this->ioMock   = $this->mock(IOInterface::class);

        $this->configurator = new ComposerScriptsConfigurator($this->composer, $this->ioMock, ['self-dir' => 'test']);

        $callback = $this->getGenericPropertyReader();

        $json = &$callback($this->configurator, 'json');
        $json = $this->jsonMock;

        $manipulator = &$callback($this->configurator, 'manipulator');
        $manipulator = $this->jsonManipulatorMock;
    }

    public function testGetName(): void
    {
        static::assertSame('composer-scripts', ComposerScriptsConfigurator::getName());
    }

    public function testConfigure(): void
    {
        $composerRootJsonString = ComposerJsonFactory::createComposerScriptJson('configure', ['auto-scripts' => []]);
        $composerRootJsonData   = ComposerJsonFactory::jsonToArray($composerRootJsonString);

        $script = ['php -v' => 'script'];

        $packageMock = $this->mock(PackageContract::class);
        $packageMock->shouldReceive('getConfiguratorOptions')
            ->once()
            ->with(ComposerScriptsConfigurator::getName())
            ->andReturn($script);

        $this->jsonMock->shouldReceive('read')
            ->andReturn($composerRootJsonData);

        $composerJsonPath = __DIR__ . '/composer.json';

        $this->jsonMock->shouldReceive('getPath')
            ->once()
            ->andReturn($composerJsonPath);

        $this->jsonManipulatorMock->shouldReceive('addSubNode')
            ->once()
            ->with('scripts', 'auto-scripts', $script);

        $composerRootJsonData['scripts']['auto-scripts'] = $script;

        $this->jsonManipulatorMock->shouldReceive('getContents')
            ->andReturn(ComposerJsonFactory::arrayToJson($composerRootJsonData));

        $this->configurator->configure($packageMock);

        \unlink($composerJsonPath);
    }

    public function testUnconfigure(): void
    {
        $composerRootJsonString = ComposerJsonFactory::createComposerScriptJson('unconfigure', ['auto-scripts' => ['php -v' => 'script', 'list' => 'cerebro-cmd']]);
        $composerRootJsonData   = ComposerJsonFactory::jsonToArray($composerRootJsonString);

        $packageMock = $this->mock(PackageContract::class);
        $packageMock->shouldReceive('getConfiguratorOptions')
            ->once()
            ->with(ComposerScriptsConfigurator::getName())
            ->andReturn(['php -v' => 'script']);

        $this->jsonMock->shouldReceive('read')
            ->andReturn($composerRootJsonData);

        $composerJsonPath = __DIR__ . '/composer.json';

        $this->jsonMock->shouldReceive('getPath')
            ->once()
            ->andReturn($composerJsonPath);

        $this->jsonManipulatorMock->shouldReceive('addSubNode')
            ->once()
            ->with('scripts', 'auto-scripts', ['list' => 'cerebro-cmd']);

        $this->jsonManipulatorMock->shouldReceive('getContents')
            ->andReturn(ComposerJsonFactory::arrayToJson($composerRootJsonData));

        $this->configurator->unconfigure($packageMock);

        \unlink($composerJsonPath);
    }
}
