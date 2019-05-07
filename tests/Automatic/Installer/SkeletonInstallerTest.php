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
     * {@inheritDoc}
     */
    protected $installerClass = SkeletonInstaller::class;

    public function testInstallWithNotFoundClasses(): void
    {
        $this->lockMock->shouldReceive('remove')
            ->once()
            ->with(SkeletonInstaller::LOCK_KEY);

        parent::testInstallWithNotFoundClasses();
    }
}
