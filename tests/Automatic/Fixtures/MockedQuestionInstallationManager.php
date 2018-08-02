<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Fixtures;

use Composer\Installer as BaseInstaller;
use Composer\Json\JsonFile;
use Composer\Package\Version\VersionSelector;
use Narrowspark\Automatic\Installer\QuestionInstallationManager;

class MockedQuestionInstallationManager extends QuestionInstallationManager
{
    private $installer;

    /**
     * @param object $installer
     */
    public function setInstaller($installer): void
    {
        $this->installer = $installer;
    }

    /**
     * @param string $composerFilePath
     */
    public function setComposerFile(string $composerFilePath): void
    {
        $this->jsonFile = new JsonFile($composerFilePath);
    }

    /**
     * @return \Composer\Package\Version\VersionSelector
     */
    public function getVersionSelector(): VersionSelector
    {
        return $this->versionSelector;
    }

    /**
     * {@inheritdoc}
     */
    protected function getInstaller(): BaseInstaller
    {
        return $this->installer;
    }
}
