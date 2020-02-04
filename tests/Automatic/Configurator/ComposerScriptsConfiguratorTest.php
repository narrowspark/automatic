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

namespace Narrowspark\Automatic\Test\Configurator;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Mockery;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Configurator\ComposerScriptsConfigurator;
use Narrowspark\Automatic\QuestionFactory;
use Narrowspark\Automatic\Test\Fixture\ComposerJsonFactory;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Configurator\ComposerScriptsConfigurator
 *
 * @medium
 */
final class ComposerScriptsConfiguratorTest extends MockeryTestCase
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

    /** @var \Narrowspark\Automatic\Configurator\ComposerScriptsConfigurator */
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

        $this->configurator = new ComposerScriptsConfigurator($this->composer, $this->ioMock, ['self-dir' => 'test']);

        $callback = $this->getGenericPropertyReader();

        $json = &$callback($this->configurator, 'json');
        $json = $this->jsonMock;

        $manipulator = &$callback($this->configurator, 'manipulator');
        $manipulator = $this->jsonManipulatorMock;
    }

    public function testGetName(): void
    {
        self::assertSame('composer-scripts', ComposerScriptsConfigurator::getName());
    }

    public function testConfigure(): void
    {
        $composerRootJsonString = ComposerJsonFactory::createAutomaticComposerJson('stub/stub');
        $composerRootJsonData = ComposerJsonFactory::jsonToArray($composerRootJsonString);

        $package = new Package('Stub/stub', '1.0.0');
        $package->setConfig([
            ConfiguratorContract::TYPE => [
                ComposerScriptsConfigurator::getName() => [
                    'post-autoload-dump' => ['Foo\\Bar'],
                ],
            ],
        ]);

        $this->jsonMock->shouldReceive('read')
            ->andReturn($composerRootJsonData);

        $composerJsonPath = __DIR__ . '/composer.json';
        \file_put_contents($composerJsonPath, \json_encode(['extra' => []]));

        $this->jsonMock->shouldReceive('getPath')
            ->once()
            ->andReturn($composerJsonPath);

        $whitelist = [ComposerScriptsConfigurator::COMPOSER_EXTRA_KEY => [ComposerScriptsConfigurator::WHITELIST => [$package->getName()]]];

        $this->jsonManipulatorMock->shouldReceive('addSubNode')
            ->once()
            ->with('extra', Automatic::COMPOSER_EXTRA_KEY, $whitelist);

        $this->jsonManipulatorMock->shouldReceive('addMainKey')
            ->once()
            ->with('scripts', ['post-autoload-dump' => ['Foo\\Bar']]);

        $composerRootJsonData['scripts']['auto-scripts'] = $whitelist;

        $this->jsonManipulatorMock->shouldReceive('getContents')
            ->andReturn(ComposerJsonFactory::arrayToJson($composerRootJsonData));

        $this->ioMock->shouldReceive('askConfirmation')
            ->once()
            ->with(QuestionFactory::getPackageScriptsQuestion($package->getPrettyName()), false)
            ->andReturn(true);

        $this->configurator->configure($package);

        \unlink($composerJsonPath);
    }

    public function testConfigureWithUpdate(): void
    {
        $oldWhitelist = [ComposerScriptsConfigurator::COMPOSER_EXTRA_KEY => [ComposerScriptsConfigurator::WHITELIST => ['stub/stub']]];

        $composerRootJsonString = ComposerJsonFactory::createAutomaticComposerJson('stub/stub', [], [], $oldWhitelist);
        $composerRootJsonData = ComposerJsonFactory::jsonToArray($composerRootJsonString);

        $package = new Package('Stub/stub', '1.0.0');
        $package->setConfig([
            ConfiguratorContract::TYPE => [
                ComposerScriptsConfigurator::getName() => [
                    'post-autoload-dump' => ['Foo\\Bar'],
                ],
            ],
        ]);

        $this->jsonMock->shouldReceive('read')
            ->andReturn($composerRootJsonData);

        $composerJsonPath = __DIR__ . '/composer.json';

        \file_put_contents($composerJsonPath, \json_encode(['extra' => []]));

        $this->jsonMock->shouldReceive('getPath')
            ->once()
            ->andReturn($composerJsonPath);

        $whitelist = [ComposerScriptsConfigurator::COMPOSER_EXTRA_KEY => [ComposerScriptsConfigurator::WHITELIST => [$package->getName()]]];

        $this->jsonManipulatorMock->shouldReceive('addSubNode')
            ->once()
            ->with('extra', Automatic::COMPOSER_EXTRA_KEY, $whitelist);

        $this->jsonManipulatorMock->shouldReceive('addMainKey')
            ->once()
            ->with('scripts', ['post-autoload-dump' => ['Foo\\Bar']]);

        $composerRootJsonData['scripts']['auto-scripts'] = $whitelist;

        $this->jsonManipulatorMock->shouldReceive('getContents')
            ->andReturn(ComposerJsonFactory::arrayToJson($composerRootJsonData));

        $this->ioMock->shouldReceive('askConfirmation')
            ->never();

        $this->configurator->configure($package);

        \unlink($composerJsonPath);
    }

    public function testConfigureWithBlacklist(): void
    {
        $blackList = [ComposerScriptsConfigurator::COMPOSER_EXTRA_KEY => [ComposerScriptsConfigurator::BLACKLIST => ['stub/stub']]];

        $composerRootJsonString = ComposerJsonFactory::createAutomaticComposerJson('stub/stub', [], [], $blackList);
        $composerRootJsonData = ComposerJsonFactory::jsonToArray($composerRootJsonString);

        $package = new Package('Stub/stub', '1.0.0');
        $package->setConfig([
            ConfiguratorContract::TYPE => [
                ComposerScriptsConfigurator::getName() => [
                    'post-autoload-dump' => ['Foo\\Bar'],
                ],
            ],
        ]);

        $this->jsonMock->shouldReceive('read')
            ->andReturn($composerRootJsonData);
        $this->ioMock->shouldReceive('write')
            ->once()
            ->with('Composer scripts for [Stub/stub] skipped, because it was found in the [blacklist]');

        $this->configurator->configure($package);
    }

    public function testConfigureNotAllowedScripts(): void
    {
        $composerRootJsonString = ComposerJsonFactory::createAutomaticComposerJson('stub/stub');
        $composerRootJsonData = ComposerJsonFactory::jsonToArray($composerRootJsonString);

        $package = new Package('Stub/stub', '1.0.0');
        $package->setConfig([
            ConfiguratorContract::TYPE => [
                ComposerScriptsConfigurator::getName() => [
                    'post-autoload-dump' => ['Foo\\Bar'],
                    'notallowed' => 'foo',
                ],
            ],
        ]);

        $this->jsonMock->shouldReceive('read')
            ->andReturn($composerRootJsonData);

        $composerJsonPath = __DIR__ . '/composer.json';
        \file_put_contents($composerJsonPath, \json_encode(['extra' => []]));

        $this->jsonMock->shouldReceive('getPath')
            ->once()
            ->andReturn($composerJsonPath);

        $whitelist = [ComposerScriptsConfigurator::COMPOSER_EXTRA_KEY => [ComposerScriptsConfigurator::WHITELIST => [$package->getName()]]];

        $this->jsonManipulatorMock->shouldReceive('addSubNode')
            ->once()
            ->with('extra', Automatic::COMPOSER_EXTRA_KEY, $whitelist);

        $this->jsonManipulatorMock->shouldReceive('addMainKey')
            ->once()
            ->with('scripts', ['post-autoload-dump' => ['Foo\\Bar']]);

        $composerRootJsonData['scripts']['auto-scripts'] = $whitelist;

        $this->jsonManipulatorMock->shouldReceive('getContents')
            ->andReturn(ComposerJsonFactory::arrayToJson($composerRootJsonData));

        $this->ioMock->shouldReceive('askConfirmation')
            ->once()
            ->with(QuestionFactory::getPackageScriptsQuestion($package->getPrettyName()), false)
            ->andReturn(true);

        $this->ioMock->shouldReceive('write')
            ->once()
            ->with(\sprintf(
                "<warning>    Found not allowed composer events [notallowed] in [%s]</>\n",
                $package->getName()
            ))
            ->andReturn(true);

        $this->configurator->configure($package);

        \unlink($composerJsonPath);
    }

    public function testConfigureWithoutScripts(): void
    {
        $package = new Package('Stub/stub', '1.0.0');

        $this->ioMock->shouldReceive('askConfirmation')
            ->never();

        $this->configurator->configure($package);
    }

    public function testUnconfigure(): void
    {
        $package = new Package('Stub/stub', '1.0.0');
        $package->setConfig([
            ConfiguratorContract::TYPE => [
                ComposerScriptsConfigurator::getName() => [
                    'post-autoload-dump' => ['Foo\\Bar'],
                    'notallowed' => 'foo',
                ],
            ],
        ]);

        $whitelist = [ComposerScriptsConfigurator::COMPOSER_EXTRA_KEY => [ComposerScriptsConfigurator::WHITELIST => [$package->getName()]]];

        $composerRootJsonString = ComposerJsonFactory::createAutomaticComposerJson('stub/stub', [], [], $whitelist);
        $composerRootJsonData = ComposerJsonFactory::jsonToArray($composerRootJsonString);

        $this->jsonMock->shouldReceive('read')
            ->andReturn($composerRootJsonData);

        $composerJsonPath = __DIR__ . '/composer.json';

        $this->jsonMock->shouldReceive('getPath')
            ->once()
            ->andReturn($composerJsonPath);

        $this->jsonManipulatorMock->shouldReceive('addSubNode')
            ->once()
            ->with('extra', Automatic::COMPOSER_EXTRA_KEY, [ComposerScriptsConfigurator::COMPOSER_EXTRA_KEY => [ComposerScriptsConfigurator::WHITELIST => []]]);

        $this->jsonManipulatorMock->shouldReceive('addMainKey')
            ->once()
            ->with('scripts', []);

        $this->jsonManipulatorMock->shouldReceive('getContents')
            ->andReturn(ComposerJsonFactory::arrayToJson($composerRootJsonData));

        $this->configurator->unconfigure($package);

        \unlink($composerJsonPath);
    }

    /**
     * {@inheritdoc}
     */
    protected function assertPreConditions(): void
    {
        parent::assertPreConditions();

        $this->allowMockingNonExistentMethods(true);
    }
}
