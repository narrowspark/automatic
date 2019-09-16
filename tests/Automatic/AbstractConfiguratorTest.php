<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Test;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\Test\Fixture\MockConfigurator;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 */
abstract class AbstractConfiguratorTest extends MockeryTestCase
{
    /** @var \Composer\Composer|\Mockery\MockInterface */
    protected $composerMock;

    /** @var \Composer\IO\IOInterface|\Mockery\MockInterface */
    protected $ioMock;

    /** @var \Narrowspark\Automatic\Configurator */
    protected $configurator;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composerMock = $this->mock(Composer::class);
        $this->ioMock = $this->mock(IOInterface::class);

        $configurator = $this->getConfiguratorClass();
        $this->configurator = new $configurator($this->composerMock, $this->ioMock, []);
    }

    public function testAdd(): void
    {
        self::assertFalse($this->configurator->has(MockConfigurator::getName()));

        $this->configurator->add(MockConfigurator::getName(), MockConfigurator::class);

        self::assertTrue($this->configurator->has(MockConfigurator::getName()));
    }

    public function testAddWithExistingConfiguratorName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configurator with the name [' . MockConfigurator::getName() . '] already exists.');

        $this->configurator->add(MockConfigurator::getName(), MockConfigurator::class);
        $this->configurator->add(MockConfigurator::getName(), MockConfigurator::class);
    }

    public function testAddWithoutConfiguratorContractClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The class [stdClass] must implement the interface [\\Narrowspark\\Automatic\\Common\\Contract\\Configurator].');

        $this->configurator->add('test', \stdClass::class);
    }

    public function testClear(): void
    {
        $this->configurator->add(MockConfigurator::getName(), MockConfigurator::class);

        self::assertTrue($this->configurator->has(MockConfigurator::getName()));

        $this->configurator->reset();

        self::assertFalse($this->configurator->has(MockConfigurator::getName()));
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    abstract protected function getConfiguratorClass(): string;
}
