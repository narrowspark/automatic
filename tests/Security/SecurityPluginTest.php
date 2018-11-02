<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Test;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderContract;
use Composer\Script\Event;
use Narrowspark\Automatic\Security\Audit;
use Narrowspark\Automatic\Security\CommandProvider;
use Narrowspark\Automatic\Security\Downloader\ComposerDownloader;
use Narrowspark\Automatic\Security\SecurityPlugin;
use Narrowspark\Automatic\Test\Traits\ArrangeComposerClasses;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Nyholm\NSA;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final class SecurityPluginTest extends MockeryTestCase
{
    use ArrangeComposerClasses;

    /**
     * @var \Narrowspark\Automatic\Security\SecurityPlugin
     */
    private $securityPlugin;

    /**
     * @var string
     */
    private $tmpFolder;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->arrangeComposerClasses();

        $this->securityPlugin = new SecurityPlugin();
        $this->tmpFolder      = __DIR__ . \DIRECTORY_SEPARATOR . 'tmp';
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem())->remove([$this->tmpFolder, __DIR__ . \DIRECTORY_SEPARATOR . 'narrowspark']);
    }

    public function testActivate(): void
    {
        $this->composerMock->shouldReceive('getPackage->getExtra')
            ->once()
            ->andReturn([SecurityPlugin::COMPOSER_EXTRA_KEY => ['timeout' => 20]]);

        $this->configMock->shouldReceive('get')
            ->once()
            ->with('vendor-dir')
            ->andReturn(__DIR__);

        $this->composerMock->shouldReceive('getConfig')
            ->andReturn($this->configMock);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Downloading the Security Advisories database...', true, IOInterface::VERBOSE);

        $this->securityPlugin->activate($this->composerMock, $this->ioMock);
    }

    public function testGetSubscribedEvents(): void
    {
        $this->assertCount(4, SecurityPlugin::getSubscribedEvents());

        NSA::setProperty($this->securityPlugin, 'activated', false);

        $this->assertCount(0, SecurityPlugin::getSubscribedEvents());
    }

    public function testGetCapabilities(): void
    {
        $this->assertSame([CommandProviderContract::class => CommandProvider::class], $this->securityPlugin->getCapabilities());
    }

    public function testOnPostUpdatePostMessages(): void
    {
        $eventMock = $this->mock(Event::class);

        $this->ioMock->shouldReceive('write')
            ->once()
            ->with('<fg=black;bg=green>[+]</> Audit Security Report: No known vulnerabilities found');

        NSA::setProperty($this->securityPlugin, 'io', $this->ioMock);

        $this->securityPlugin->onPostUpdatePostMessages($eventMock);
    }

    public function testOnPostUpdatePostMessagesWithVulnerability(): void
    {
        $eventMock = $this->mock(Event::class);

        NSA::setProperty($this->securityPlugin, 'foundVulnerabilities', ['test']);

        $this->ioMock->shouldReceive('write')
            ->once()
            ->with('<error>[!]</> Audit Security Report: 1 vulnerability found - run "composer audit" for more information');

        NSA::setProperty($this->securityPlugin, 'io', $this->ioMock);

        $this->securityPlugin->onPostUpdatePostMessages($eventMock);
    }

    public function testAuditPackage(): void
    {
        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getName')
            ->once()
            ->andReturn('symfony/symfony');
        $packageMock->shouldReceive('getVersion')
            ->once()
            ->andReturn('v2.5.2');

        $operationMock = $this->mock(InstallOperation::class);
        $operationMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($packageMock);

        $eventMock = $this->mock(PackageEvent::class);
        $eventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($operationMock);

        $audit = new Audit($this->tmpFolder, new ComposerDownloader());

        NSA::setProperty($this->securityPlugin, 'audit', $audit);
        NSA::setProperty($this->securityPlugin, 'securityAdvisories', $audit->getSecurityAdvisories());

        $this->securityPlugin->auditPackage($eventMock);

        $this->assertCount(1, NSA::getProperty($this->securityPlugin, 'foundVulnerabilities'));
    }

    public function testAuditPackageWithUninstall(): void
    {
        $operationMock = $this->mock(UninstallOperation::class);

        $eventMock = $this->mock(PackageEvent::class);
        $eventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($operationMock);

        $this->securityPlugin->auditPackage($eventMock);

        $this->assertCount(0, NSA::getProperty($this->securityPlugin, 'foundVulnerabilities'));
    }

    public function testAuditPackageWithUpdate(): void
    {
        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getName')
            ->once()
            ->andReturn('symfony/view');
        $packageMock->shouldReceive('getVersion')
            ->once()
            ->andReturn('v4.1.0');

        $operationMock = $this->mock(UpdateOperation::class);
        $operationMock->shouldReceive('getTargetPackage')
            ->once()
            ->andReturn($packageMock);

        $eventMock = $this->mock(PackageEvent::class);
        $eventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($operationMock);

        $audit = new Audit($this->tmpFolder, new ComposerDownloader());

        NSA::setProperty($this->securityPlugin, 'audit', $audit);
        NSA::setProperty($this->securityPlugin, 'securityAdvisories', $audit->getSecurityAdvisories());

        $this->securityPlugin->auditPackage($eventMock);

        $this->assertCount(0, NSA::getProperty($this->securityPlugin, 'foundVulnerabilities'));
    }

    public function testAuditComposerLock(): void
    {
        \putenv('COMPOSER=' . __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.json');

        $audit = new Audit($this->tmpFolder, new ComposerDownloader());

        NSA::setProperty($this->securityPlugin, 'audit', $audit);

        $this->securityPlugin->auditComposerLock($this->mock(Event::class));

        $this->assertCount(1, NSA::getProperty($this->securityPlugin, 'foundVulnerabilities'));

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
