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
        $this->packageMock->shouldReceive('getPrettyVersion')
            ->once()
            ->andReturn('dev-master');

        parent::testInstall();
    }

    public function testUpdate(): void
    {
        $this->targetPackageMock->shouldReceive('getPrettyVersion')
            ->once()
            ->andReturn('dev-master');

        parent::testUpdate();
    }
}
