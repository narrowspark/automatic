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

namespace Narrowspark\Automatic\Tests\Operation\Traits;

use Composer\Composer;
use Composer\IO\IOInterface;
use Mockery;
use Narrowspark\Automatic\Common\ClassFinder;
use Narrowspark\Automatic\Configurator;
use Narrowspark\Automatic\Lock;
use Narrowspark\Automatic\PackageConfigurator;

trait ArrangeOperationsClassesTrait
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

        $this->lockMock = Mockery::mock(Lock::class);
        $this->ioMock = Mockery::mock(IOInterface::class);
        $this->composerMock = Mockery::mock(Composer::class);

        $this->configurator = new Configurator($this->composerMock, $this->ioMock, []);
        $this->packageConfigurator = new PackageConfigurator($this->composerMock, $this->ioMock, []);
        $this->classFinder = new ClassFinder($this->fixturePath);
    }
}
