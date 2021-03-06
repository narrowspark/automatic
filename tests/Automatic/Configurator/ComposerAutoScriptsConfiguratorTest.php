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

namespace Narrowspark\Automatic\Tests\Configurator;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Mockery;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Configurator\ComposerAutoScriptsConfigurator;
use Narrowspark\Automatic\Tests\Fixture\ComposerJsonFactory;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Configurator\ComposerAutoScriptsConfigurator
 *
 * @medium
 */
final class ComposerAutoScriptsConfiguratorTest extends MockeryTestCase
{
    use GetGenericPropertyReaderTrait;

    /** @var \Composer\Composer */
    private $composer;

    /** @var \Composer\IO\IOInterface|\Mockery\MockInterface */
    private $ioMock;

    /** @var \Composer\Json\JsonFile|\Mockery\MockInterface */
    private $jsonMock;

    /** @var \Composer\Json\JsonManipulator|\Mockery\MockInterface */
    private $jsonManipulatorMock;

    /** @var \Narrowspark\Automatic\Configurator\ComposerAutoScriptsConfigurator */
    private $configurator;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonMock = Mockery::mock(JsonFile::class);
        $this->jsonManipulatorMock = Mockery::mock(JsonManipulator::class);

        $this->composer = new Composer();
        $this->ioMock = Mockery::mock(IOInterface::class);

        $this->configurator = new ComposerAutoScriptsConfigurator($this->composer, $this->ioMock, ['self-dir' => 'test']);

        $callback = $this->getGenericPropertyReader();

        $json = &$callback($this->configurator, 'json');
        $json = $this->jsonMock;

        $manipulator = &$callback($this->configurator, 'manipulator');
        $manipulator = $this->jsonManipulatorMock;
    }

    public function testGetName(): void
    {
        self::assertSame('composer-auto-scripts', ComposerAutoScriptsConfigurator::getName());
    }

    public function testConfigure(): void
    {
        $composerRootJsonString = ComposerJsonFactory::createComposerScriptJson('configure', ['auto-scripts' => []]);
        $composerRootJsonData = ComposerJsonFactory::jsonToArray($composerRootJsonString);

        $script = ['php -v' => 'script'];

        $packageMock = Mockery::mock(PackageContract::class);
        $packageMock->shouldReceive('getConfig')
            ->once()
            ->with(ConfiguratorContract::TYPE, ComposerAutoScriptsConfigurator::getName())
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
        $composerRootJsonData = ComposerJsonFactory::jsonToArray($composerRootJsonString);

        $packageMock = Mockery::mock(PackageContract::class);
        $packageMock->shouldReceive('getConfig')
            ->once()
            ->with(ConfiguratorContract::TYPE, ComposerAutoScriptsConfigurator::getName())
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

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods(bool $allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
