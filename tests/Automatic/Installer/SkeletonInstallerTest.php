<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Test\Installer;

use Narrowspark\Automatic\Installer\SkeletonInstaller;

/**
 * @internal
 *
 * @small
 */
final class SkeletonInstallerTest extends AbstractInstallerTest
{
    /**
     * {@inheritdoc}
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
