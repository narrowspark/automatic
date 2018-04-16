<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Discovery\Configurator;
use Narrowspark\Discovery\Discovery;
use Narrowspark\Discovery\Lock;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

class DiscoveryTest extends MockeryTestCase
{
    public function testGetNarrowsparkLockFile(): void
    {
        self::assertSame('./narrowspark.lock', Discovery::getNarrowsparkLockFile());
    }

    public function testActivate(): void
    {
        $this->allowMockingNonExistentMethods(true);

        $disovery = new Discovery();

        $composerMock = $this->mock(Composer::class);
        $ioMock       = $this->mock(IOInterface::class);

        $composerMock->shouldReceive('getPackage->getExtra')
            ->once()
            ->andReturn([]);
        $composerMock->shouldReceive('getConfig->get')
            ->once()
            ->with('vendor-dir');

        $disovery->activate($composerMock, $ioMock);

        self::assertInstanceOf(Lock::class, $disovery->getLock());
        self::assertInstanceOf(Configurator::class, $disovery->getConfigurator());

        self::assertSame(
            [
                'This file locks the narrowspark information of your project to a known state',
                'This file is @generated automatically',
            ],
            $disovery->getLock()->get('_readme')
        );
        self::assertInternalType('string', $disovery->getLock()->get('content-hash'));

        $this->allowMockingNonExistentMethods();
    }
}
