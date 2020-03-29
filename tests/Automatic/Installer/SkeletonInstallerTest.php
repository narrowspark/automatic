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

namespace Narrowspark\Automatic\Tests\Installer;

use Narrowspark\Automatic\Installer\SkeletonInstaller;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Installer\SkeletonInstaller
 *
 * @medium
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
