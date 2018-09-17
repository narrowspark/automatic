<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security;

use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\Script\Event;

trait SecurityPluginTrait
{
    /**
     * The SecurityAdvisories database.
     *
     * @var array<string, array>
     */
    protected $securityAdvisories;

    /**
     * Found package vulnerabilities.
     *
     * @var array[]
     */
    protected $foundVulnerabilities = [];

    /**
     * Audit composer package operations.
     *
     * @param \Composer\Installer\PackageEvent $event
     *
     * @return void
     */
    public function auditPackage(PackageEvent $event): void
    {
        $operation = $event->getOperation();

        if ($operation instanceof UninstallOperation) {
            return;
        }

        if ($operation instanceof UpdateOperation) {
            $composerPackage = $operation->getTargetPackage();
        } else {
            $composerPackage = $operation->getPackage();
        }

        $data = $this->container->get(Audit::class)->checkPackage(
            $composerPackage->getName(),
            $composerPackage->getVersion(),
            $this->securityAdvisories
        );

        if (\count($data) === 0) {
            return;
        }

        $this->foundVulnerabilities += $data[0];
    }

    /**
     * Audit composer.lock.
     *
     * @param \Composer\Script\Event $event
     *
     * @return void
     */
    public function auditComposerLock(Event $event): void
    {
        if (\count($this->foundVulnerabilities) !== 0) {
            return;
        }

        $data = $this->container->get(Audit::class)->checkLock(Util::getComposerLockFile());

        if (\count($data) === 0) {
            return;
        }

        $this->foundVulnerabilities += $data[0];
    }
}
