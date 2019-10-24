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
