<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Test;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\IO\NullIO;
use Composer\Package\PackageInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderContract;
use Composer\Repository\RepositoryManager;
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
            ->with('Downloading the Security Advisories database...');

        $this->securityPlugin->activate($this->composerMock, $this->ioMock);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertCount(7, SecurityPlugin::getSubscribedEvents());

        NSA::setProperty($this->securityPlugin, 'activated', false);

        static::assertCount(0, SecurityPlugin::getSubscribedEvents());
    }

    public function testGetCapabilities(): void
    {
        static::assertSame([CommandProviderContract::class => CommandProvider::class], $this->securityPlugin->getCapabilities());
    }

    public function testPostMessages(): void
    {
        $eventMock = $this->mock(Event::class);
        $eventMock->shouldReceive('stopPropagation')
            ->once();

        $this->ioMock->shouldReceive('write')
            ->once()
            ->with('<fg=black;bg=green>[+]</> Audit Security Report: No known vulnerabilities found');

        NSA::setProperty($this->securityPlugin, 'io', $this->ioMock);

        $this->securityPlugin->postMessages($eventMock);
    }

    public function testPostMessagesWithVulnerability(): void
    {
        $eventMock = $this->mock(Event::class);
        $eventMock->shouldReceive('stopPropagation')
            ->once();

        NSA::setProperty($this->securityPlugin, 'foundVulnerabilities', ['test']);

        $this->ioMock->shouldReceive('write')
            ->once()
            ->with('<error>[!]</> Audit Security Report: 1 vulnerability found - run "composer audit" for more information');

        NSA::setProperty($this->securityPlugin, 'io', $this->ioMock);

        $this->securityPlugin->postMessages($eventMock);
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

        static::assertCount(1, NSA::getProperty($this->securityPlugin, 'foundVulnerabilities'));
    }

    public function testAuditPackageWithUninstall(): void
    {
        $operationMock = $this->mock(UninstallOperation::class);

        $eventMock = $this->mock(PackageEvent::class);
        $eventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($operationMock);

        $this->securityPlugin->auditPackage($eventMock);

        static::assertCount(0, NSA::getProperty($this->securityPlugin, 'foundVulnerabilities'));
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

        static::assertCount(0, NSA::getProperty($this->securityPlugin, 'foundVulnerabilities'));
    }

    public function testAuditComposerLock(): void
    {
        \putenv('COMPOSER=' . __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.json');

        $audit = new Audit($this->tmpFolder, new ComposerDownloader());

        NSA::setProperty($this->securityPlugin, 'audit', $audit);

        $this->securityPlugin->auditComposerLock($this->mock(Event::class));

        static::assertCount(1, NSA::getProperty($this->securityPlugin, 'foundVulnerabilities'));

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    public function testInitMessage(): void
    {
        $composerJsonPath = __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'composer_on_init.json';
        $composerLockPath = \mb_substr($composerJsonPath, 0, -4) . 'lock';

        \file_put_contents($composerJsonPath, \json_encode(['test' => []]));
        \file_put_contents($composerLockPath, \json_encode(['packages' => []]));

        \putenv('COMPOSER=' . $composerJsonPath);

        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getScripts')
            ->once()
            ->andReturn([]);

        $this->composerMock
            ->shouldReceive('getPackage')
            ->once()
            ->andReturn($packageMock);

        $repositoryManagerMock = $this->mock(RepositoryManager::class);

        $this->composerMock->shouldReceive('getRepositoryManager')
            ->once()
            ->andReturn($repositoryManagerMock);

        $installationManagerMock = $this->mock(InstallationManager::class);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManagerMock);

        NSA::setProperty($this->securityPlugin, 'composer', $this->composerMock);
        NSA::setProperty($this->securityPlugin, 'io', new NullIO());

        $this->securityPlugin->initMessage();

        $jsonContent = \json_decode(\file_get_contents($composerJsonPath), true);

        static::assertTrue(isset($jsonContent['scripts']));
        static::assertTrue(isset($jsonContent['scripts']['post-messages']));
        static::assertTrue(isset($jsonContent['scripts']['post-install-cmd']));
        static::assertTrue(isset($jsonContent['scripts']['post-update-cmd']));
        static::assertSame('@post-messages', $jsonContent['scripts']['post-install-cmd'][0]);
        static::assertSame('@post-messages', $jsonContent['scripts']['post-update-cmd'][0]);

        $lockContent = \json_decode(\file_get_contents($composerLockPath), true);

        static::assertInternalType('string', $lockContent['content-hash']);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
        @\unlink($composerJsonPath);
        @\unlink($composerLockPath);
    }

    public function testInitMessageWithPostMessages(): void
    {
        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getScripts')
            ->once()
            ->andReturn(['post-messages' => '']);

        $this->composerMock
            ->shouldReceive('getPackage')
            ->once()
            ->andReturn($packageMock);

        NSA::setProperty($this->securityPlugin, 'composer', $this->composerMock);

        $this->securityPlugin->initMessage();
    }

    public function testOnPostUninstall(): void
    {
        $composerJsonPath = __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'composer_on_init.json';
        $composerLockPath = \mb_substr($composerJsonPath, 0, -4) . 'lock';

        $scripts = ['post-messages' => 'test', 'post-install-cmd' => ['@post-messages', 'foo'], 'post-update-cmd' => ['@post-messages', 'bar']];

        \file_put_contents($composerJsonPath, \json_encode(['name' => 'test', 'scripts' => $scripts]));
        \file_put_contents($composerLockPath, \json_encode(['packages' => []]));

        \putenv('COMPOSER=' . $composerJsonPath);

        $packageMock = $this->mock(PackageInterface::class);
        $packageMock->shouldReceive('getScripts')
            ->once()
            ->andReturn($scripts);

        $this->composerMock
            ->shouldReceive('getPackage')
            ->once()
            ->andReturn($packageMock);

        $repositoryManagerMock = $this->mock(RepositoryManager::class);

        $this->composerMock->shouldReceive('getRepositoryManager')
            ->once()
            ->andReturn($repositoryManagerMock);

        $installationManagerMock = $this->mock(InstallationManager::class);

        $this->composerMock->shouldReceive('getInstallationManager')
            ->once()
            ->andReturn($installationManagerMock);

        NSA::setProperty($this->securityPlugin, 'io', new NullIO());
        NSA::setProperty($this->securityPlugin, 'composer', $this->composerMock);

        $event = $this->mock(PackageEvent::class);
        $event->shouldReceive('getOperation->getPackage->getName')
            ->andReturn(SecurityPlugin::PACKAGE_NAME);

        $this->securityPlugin->onPostUninstall($event);

        $jsonContent = \json_decode(\file_get_contents($composerJsonPath), true);

        static::assertTrue(isset($jsonContent['scripts']));
        static::assertFalse(isset($jsonContent['scripts']['post-messages']));
        static::assertTrue(isset($jsonContent['scripts']['post-install-cmd']));
        static::assertTrue(isset($jsonContent['scripts']['post-update-cmd']));
        static::assertCount(1, $jsonContent['scripts']['post-install-cmd']);
        static::assertCount(1, $jsonContent['scripts']['post-update-cmd']);

        $lockContent = \json_decode(\file_get_contents($composerLockPath), true);

        static::assertInternalType('string', $lockContent['content-hash']);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
        @\unlink($composerJsonPath);
        @\unlink($composerLockPath);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
