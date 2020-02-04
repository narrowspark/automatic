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

namespace Narrowspark\Automatic\Test\Traits;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Mockery;
use Narrowspark\Automatic\Lock;
use Symfony\Component\Console\Input\InputInterface;

trait ArrangeComposerClassesTrait
{
    /** @var \Composer\Composer|\Mockery\MockInterface */
    protected $composerMock;

    /** @var \Composer\Config|\Mockery\MockInterface */
    protected $configMock;

    /** @var \Mockery\MockInterface|\Symfony\Component\Console\Input\InputInterface */
    protected $inputMock;

    /** @var \Composer\IO\IOInterface|\Mockery\MockInterface */
    protected $ioMock;

    /** @var string */
    protected $composerCachePath;

    /** @var \Mockery\MockInterface|\Narrowspark\Automatic\Lock */
    protected $lockMock;

    protected function arrangeComposerClasses(): void
    {
        $this->composerMock = Mockery::mock(Composer::class);
        $this->configMock = Mockery::mock(Config::class);
        $this->ioMock = Mockery::mock(IOInterface::class);
        $this->inputMock = Mockery::mock(InputInterface::class);
        $this->lockMock = Mockery::mock(Lock::class);
    }

    protected function arrangePackagist(): void
    {
        $this->ioMock->shouldReceive('hasAuthentication')
            ->andReturn(false);
        $this->ioMock->shouldReceive('writeError')
            ->with('Downloading https://packagist.org/packages.json', true, IOInterface::DEBUG);
    }
}
