<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Fixtures;

use Composer\Installer as BaseInstaller;
use Narrowspark\Discovery\Installer\QuestionInstallationManager;

class MockedQuestionInstallationManager extends QuestionInstallationManager
{
    private $installer;

    public function setInstaller($installer): void
    {
        $this->installer = $installer;
    }

    /**
     * {@inheritdoc}
     */
    protected function getInstaller(): BaseInstaller
    {
        return $this->installer;
    }
}
