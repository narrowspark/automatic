<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Installer\Traits;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Input\InputInterface;

trait ArrangeComposerClasses
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

    protected function arrangeComposerClasses(): void
    {
        $this->composerMock = $this->mock(Composer::class);
        $this->configMock   = $this->mock(Config::class);
        $this->ioMock       = $this->mock(IOInterface::class);
        $this->inputMock    = $this->mock(InputInterface::class);
    }
}
