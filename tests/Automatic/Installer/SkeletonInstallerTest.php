<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Installer;

use Narrowspark\Automatic\Automatic;
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
            ->with(\sprintf('Automatic lock keys [%s], [%s] were overwritten.', $this->installerClass::LOCK_KEY, Automatic::LOCK_CLASSMAP));

        parent::testInstall();
    }

    public function testUpdate(): void
    {
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(\sprintf('Automatic lock keys [%s], [%s] were overwritten.', $this->installerClass::LOCK_KEY, Automatic::LOCK_CLASSMAP));

        parent::testUpdate();
    }
}
