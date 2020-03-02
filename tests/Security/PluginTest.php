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

namespace Narrowspark\Automatic\Security\Test;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderContract;
use Composer\Script\Event;
use Mockery;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Security\CommandProvider;
use Narrowspark\Automatic\Security\Contract\Audit as AuditContract;
use Narrowspark\Automatic\Security\Plugin;
use Narrowspark\Automatic\Test\Traits\ArrangeComposerClassesTrait;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Nyholm\NSA;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Security\Plugin
 *
 * @medium
 */
final class PluginTest extends MockeryTestCase
{
    use ArrangeComposerClassesTrait;

    /** @var \Narrowspark\Automatic\Security\Plugin */
    private $plugin;

    /** @var string */
    private $tmpFolder;

    /** @var \Mockery\MockInterface|\Narrowspark\Automatic\Common\Contract\Container */
    private $containerMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->arrangeComposerClasses();

        $this->plugin = new Plugin();

        $this->containerMock = Mockery::mock(ContainerContract::class);

        NSA::setProperty($this->plugin, 'container', $this->containerMock);

        $this->tmpFolder = __DIR__ . \DIRECTORY_SEPARATOR . 'tmp';
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
            ->andReturn([Plugin::COMPOSER_EXTRA_KEY => ['timeout' => 20]]);

        $name = 'no-dev';

        /** @var \Mockery\MockInterface|\Symfony\Component\Console\Input\Input $inputMock */
        $inputMock = Mockery::mock(InputInterface::class);
        $inputMock->shouldReceive('hasOption')
            ->with($name)
            ->andReturn(true);
        $inputMock->shouldReceive('getOption')
            ->with($name)
            ->andReturn(true);

        $this->ioMock->input = $inputMock;
        $this->ioMock->shouldReceive('writeError')
            ->with('');

        $this->configMock->shouldReceive('get')
            ->with('disable-tls')
            ->andReturn(false);
        $this->configMock->shouldReceive('get')
            ->with('cafile')
            ->andReturn('');
        $this->configMock->shouldReceive('get')
            ->with('capath')
            ->andReturn('');
        $this->configMock->shouldReceive('get')
            ->with('cache-repo-dir')
            ->andReturn('');

        $this->composerMock->shouldReceive('getConfig')
            ->andReturn($this->configMock);

        $this->plugin->activate($this->composerMock, $this->ioMock);
    }

    public function testGetSubscribedEvents(): void
    {
        self::assertCount(4, Plugin::getSubscribedEvents());

        NSA::setProperty($this->plugin, 'activated', false);

        self::assertCount(0, Plugin::getSubscribedEvents());
    }

    public function testGetCapabilities(): void
    {
        self::assertSame([CommandProviderContract::class => CommandProvider::class], $this->plugin->getCapabilities());
    }

    public function testOnPostUpdatePostMessages(): void
    {
        /** @var \Composer\Script\Event|\Mockery\MockInterface $eventMock */
        $eventMock = Mockery::mock(Event::class);

        $this->ioMock->shouldReceive('write')
            ->once()
            ->with('<fg=black;bg=green>[+]</> Audit Security Report: No known vulnerabilities found');

        $this->containerMock->shouldReceive('get')
            ->with(IOInterface::class)
            ->andReturn($this->ioMock);

        $this->plugin->onPostUpdatePostMessages($eventMock);
    }

    public function testOnPostUpdatePostMessagesWithVulnerability(): void
    {
        NSA::setProperty($this->plugin, 'foundVulnerabilities', ['test']);

        $this->ioMock->shouldReceive('write')
            ->once()
            ->with('<error>[!]</> Audit Security Report: 1 vulnerability found - run "composer audit" for more information');

        $this->containerMock->shouldReceive('get')
            ->with(IOInterface::class)
            ->andReturn($this->ioMock);

        /** @var \Composer\Script\Event|\Mockery\MockInterface $eventMock */
        $eventMock = Mockery::mock(Event::class);

        $this->plugin->onPostUpdatePostMessages($eventMock);
    }

    public function testAuditPackage(): void
    {
        /** @var \Composer\Package\PackageInterface|\Mockery\MockInterface $packageMock */
        $packageMock = Mockery::mock(PackageInterface::class);
        $packageMock->shouldReceive('getName')
            ->once()
            ->andReturn('symfony/symfony');
        $packageMock->shouldReceive('getVersion')
            ->once()
            ->andReturn('v2.5.2');

        $operationMock = Mockery::mock(InstallOperation::class);
        $operationMock->shouldReceive('getPackage')
            ->once()
            ->andReturn($packageMock);

        $eventMock = Mockery::mock(PackageEvent::class);
        $eventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($operationMock);

        $auditMock = Mockery::mock(AuditContract::class);
        $auditMock->shouldReceive('checkPackage')
            ->once()
            ->andReturn([
                [
                    'symfony/symfony' => [
                        'version' => 'v2.5.2',
                        'advisories' => [],
                    ],
                ],
                [],
            ]);

        $this->containerMock->shouldReceive('get')
            ->with(AuditContract::class)
            ->andReturn($auditMock);
        $this->containerMock->shouldReceive('get')
            ->with('security_advisories')
            ->andReturn([]);

        $this->plugin->auditPackage($eventMock);

        self::assertCount(1, NSA::getProperty($this->plugin, 'foundVulnerabilities'));
    }

    public function testAuditPackageWithUninstall(): void
    {
        $operationMock = Mockery::mock(UninstallOperation::class);
        $operationMock->shouldReceive('getPackage->getPrettyName')
            ->once()
            ->andReturn(Plugin::PACKAGE_NAME);

        $eventMock = Mockery::mock(PackageEvent::class);
        $eventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($operationMock);

        $this->plugin->auditPackage($eventMock);

        self::assertCount(0, NSA::getProperty($this->plugin, 'foundVulnerabilities'));
    }

    public function testAuditPackageWithUpdate(): void
    {
        $packageMock = Mockery::mock(PackageInterface::class);
        $packageMock->shouldReceive('getName')
            ->once()
            ->andReturn('symfony/view');
        $packageMock->shouldReceive('getVersion')
            ->once()
            ->andReturn('v4.1.0');

        $operationMock = Mockery::mock(UpdateOperation::class);
        $operationMock->shouldReceive('getTargetPackage')
            ->once()
            ->andReturn($packageMock);

        $eventMock = Mockery::mock(PackageEvent::class);
        $eventMock->shouldReceive('getOperation')
            ->once()
            ->andReturn($operationMock);

        $auditMock = Mockery::mock(AuditContract::class);
        $auditMock->shouldReceive('checkPackage')
            ->once()
            ->andReturn([]);

        $this->containerMock->shouldReceive('get')
            ->with(AuditContract::class)
            ->andReturn($auditMock);
        $this->containerMock->shouldReceive('get')
            ->with('security_advisories')
            ->andReturn([]);

        $this->plugin->auditPackage($eventMock);

        self::assertCount(0, NSA::getProperty($this->plugin, 'foundVulnerabilities'));
    }

    public function testAuditComposerLock(): void
    {
        \putenv('COMPOSER=' . __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.json');

        $auditMock = Mockery::mock(AuditContract::class);
        $auditMock->shouldReceive('checkLock')
            ->once()
            ->andReturn([
                [
                    'symfony/symfony' => [
                        'version' => 'v2.5.2',
                        'advisories' => [],
                    ],
                    'twig/twig' => [
                        'version' => 'v2.0.0',
                        'advisories' => [],
                    ],
                ],
                [],
            ]);

        $this->containerMock->shouldReceive('get')
            ->with(AuditContract::class)
            ->andReturn($auditMock);

        $this->plugin->auditComposerLock();

        self::assertCount(2, NSA::getProperty($this->plugin, 'foundVulnerabilities'));

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
