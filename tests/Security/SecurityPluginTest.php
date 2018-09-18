<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Test;

use Composer\Plugin\Capability\CommandProvider as CommandProviderContract;
use Composer\Script\Event;
use Narrowspark\Automatic\Security\CommandProvider;
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
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->arrangeComposerClasses();

        $this->securityPlugin = new SecurityPlugin();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem())->remove(__DIR__ . \DIRECTORY_SEPARATOR . 'narrowspark');
    }

    public function testActivate(): void
    {
        $this->composerMock->shouldReceive('getPackage->getExtra')
            ->once()
            ->andReturn([SecurityPlugin::COMPOSER_EXTRA_KEY => ['audit' => ['timeout' => 20]]]);

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
        static::assertCount(6, SecurityPlugin::getSubscribedEvents());

        NSA::setProperty($this->securityPlugin, 'activated', false);

        static::assertCount(0, SecurityPlugin::getSubscribedEvents());
    }

    public function testGetCapabilities(): void
    {
        static::assertSame([CommandProviderContract::class => CommandProvider::class], $this->securityPlugin->getCapabilities());
    }

    public function testPostInstallOut(): void
    {
        $eventMock = $this->mock(Event::class);
        $eventMock->shouldReceive('stopPropagation')
            ->once();

        $this->ioMock->shouldReceive('write')
            ->once()
            ->with('<fg=black;bg=green>[+]</> Audit Security Report: No known vulnerabilities found');

        NSA::setProperty($this->securityPlugin, 'io', $this->ioMock);

        $this->securityPlugin->postInstallOut($eventMock);
    }

    public function testPostInstallOutWithVulnerability(): void
    {
        $eventMock = $this->mock(Event::class);
        $eventMock->shouldReceive('stopPropagation')
            ->once();

        NSA::setProperty($this->securityPlugin, 'foundVulnerabilities', ['test']);

        $this->ioMock->shouldReceive('write')
            ->once()
            ->with('<error>[!]</> Audit Security Report: 1 vulnerability found - run "composer audit" for more information');

        NSA::setProperty($this->securityPlugin, 'io', $this->ioMock);

        $this->securityPlugin->postInstallOut($eventMock);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
