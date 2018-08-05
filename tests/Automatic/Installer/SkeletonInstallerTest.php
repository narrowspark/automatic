<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Installer;

use Narrowspark\Automatic\Installer\SkeletonInstaller;

/**
 * @internal
 */
final class SkeletonInstallerTest extends AbstractInstallerTest
{
    /**
     * {@inheritdoc}
     */
    protected $installerClass = SkeletonInstaller::class;

    public function testInstall(): void
    {
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(\sprintf('Automatic lock key [%s] was overwritten.', $this->installerClass::LOCK_KEY));

        parent::testInstall();
    }

    public function testUpdate(): void
    {
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(\sprintf('Automatic lock key [%s] was overwritten.', $this->installerClass::LOCK_KEY));

        parent::testUpdate();
    }
}
