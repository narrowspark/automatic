<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Configurator;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Configurator\ComposerScriptsConfigurator;
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
     * @var \Narrowspark\Automatic\Configurator\ComposerScriptsConfigurator
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
}
