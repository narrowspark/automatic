<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Traits;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Lock;
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

    /**
     * @var string
     */
    protected $composerCachePath;

    /**
     * @var \Mockery\MockInterface|\Narrowspark\Automatic\Lock
     */
    protected $lockMock;

    protected function arrangeComposerClasses(): void
    {
        $this->composerMock = $this->mock(Composer::class);
        $this->configMock   = $this->mock(Config::class);
        $this->ioMock       = $this->mock(IOInterface::class);
        $this->inputMock    = $this->mock(InputInterface::class);
        $this->lockMock     = $this->mock(Lock::class);
    }

    protected function arrangePackagist(): void
    {
        $this->ioMock->shouldReceive('hasAuthentication')
            ->andReturn(false);
        $this->ioMock->shouldReceive('writeError')
            ->with('Downloading https://packagist.org/packages.json', true, IOInterface::DEBUG);
    }
}
