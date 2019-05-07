<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Installer;

use Composer\Installer\InstallerEvents;
use Composer\Installer\PackageEvents;
use Composer\Plugin\PluginEvents;
use Narrowspark\Automatic\Installer\InstallationManager;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 */
final class InstallationManagerTest extends MockeryTestCase
{
    /**
     * @var \Narrowspark\Automatic\Installer\InstallationManager
     */
    protected $manager;

//    protected function setUp()
//    {
//        parent::setUp();
//
//        $this->manager = new InstallationManager();
//    }

    public function testGetSubscribedEvents(): void
    {
        $this->assertSame(
            InstallationManager::getSubscribedEvents(),
            [
                InstallerEvents::PRE_DEPENDENCIES_SOLVING  => [['onPreDependenciesSolving', \PHP_INT_MAX]],
                InstallerEvents::POST_DEPENDENCIES_SOLVING => [['populateFilesCacheDir', \PHP_INT_MAX]],
                PackageEvents::PRE_PACKAGE_INSTALL         => [['populateFilesCacheDir', ~\PHP_INT_MAX]],
                PackageEvents::PRE_PACKAGE_UPDATE          => [['populateFilesCacheDir', ~\PHP_INT_MAX]],
                PluginEvents::PRE_FILE_DOWNLOAD            => 'onFileDownload',
            ]
        );
    }
}
