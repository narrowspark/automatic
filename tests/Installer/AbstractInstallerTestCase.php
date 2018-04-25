<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Installer;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Symfony\Component\Console\Input\InputInterface;

abstract class AbstractInstallerTestCase extends MockeryTestCase
{
    /**
     * @var \Composer\Composer|\Mockery\MockInterface
     */
    protected $composerMock;

    /**
     * @var \Composer\Config|\Mockery\MockInterface
     */
    protected $configMock;

    /**
     * @var \Mockery\MockInterface|\Symfony\Component\Console\Input\InputInterface
     */
    protected $inputMock;

    /**
     * @var \Composer\IO\IOInterface|\Mockery\MockInterface
     */
    protected $ioMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composerMock = $this->mock(Composer::class);
        $this->configMock   = $this->mock(Config::class);
        $this->ioMock       = $this->mock(IOInterface::class);
        $this->inputMock    = $this->mock(InputInterface::class);
    }

    /**
     * @param bool        $optimize
     * @param bool        $classmap
     * @param null|string $preferred
     */
    protected function setupInstallerConfig(bool $optimize, bool $classmap, ?string $preferred): void
    {
        $this->configMock->shouldReceive('get')
            ->with('optimize-autoloader')
            ->once()
            ->andReturn($optimize);
        $this->configMock->shouldReceive('get')
            ->with('classmap-authoritative')
            ->once()
            ->andReturn($classmap);
        $this->configMock->shouldReceive('get')
            ->with('preferred-install')
            ->once()
            ->andReturn($preferred);

        $this->composerMock->shouldReceive('getConfig')
            ->andReturn($this->configMock);
    }
}
