<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\Automatic\Test\Operation\Traits;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\ClassFinder;
use Narrowspark\Automatic\Configurator;
use Narrowspark\Automatic\Lock;
use Narrowspark\Automatic\PackageConfigurator;

trait ArrangeOperationsClasses
{
    /** @var \Narrowspark\Automatic\Configurator */
    protected $configurator;

    /** @var \Narrowspark\Automatic\PackageConfigurator */
    protected $packageConfigurator;

    /** @var \Composer\IO\IOInterface|\Mockery\MockInterface */
    protected $ioMock;

    /** @var \Mockery\MockInterface|\Narrowspark\Automatic\Lock */
    protected $lockMock;

    /** @var \Narrowspark\Automatic\Common\ClassFinder */
    protected $classFinder;

    /** @var \Composer\Composer|\Mockery\MockInterface */
    protected $composerMock;

    /** @var string */
    private $fixturePath;

    protected function arrangeOperationsClasses(): void
    {
        $this->fixturePath = \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'Fixture';

        $this->lockMock = $this->mock(Lock::class);
        $this->ioMock = $this->mock(IOInterface::class);
        $this->composerMock = $this->mock(Composer::class);

        $this->configurator = new Configurator($this->composerMock, $this->ioMock, []);
        $this->packageConfigurator = new PackageConfigurator($this->composerMock, $this->ioMock, []);
        $this->classFinder = new ClassFinder($this->fixturePath);
    }
}
