<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\Automatic\Security\Test;

use Narrowspark\Automatic\Security\Command\AuditCommand;
use PackageVersions\Versions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @small
 */
final class AuditCommandTest extends TestCase
{
    /** @var \Composer\Console\Application */
    private $application;

    /** @var string */
    private $greenString;

    /** @var string */
    private $redString;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->application = new Application();

        $consoleVersion = \version_compare(Versions::getVersion('symfony/console'), '3.0.0', '<');

        $this->greenString = $consoleVersion ? '' : '[+]';
        $this->redString = $consoleVersion ? '' : '[!]';
    }

    public function testAuditCommand(): void
    {
        \putenv('COMPOSER=' . \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'composer_1.7.1_composer.lock');

        $commandTester = $this->executeCommand(new AuditCommand());

        self::assertStringContainsString($this->greenString . ' No known vulnerabilities found', \trim($commandTester->getDisplay(true)));

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    public function testAuditCommandWithComposerLockOption(): void
    {
        $commandTester = $this->executeCommand(
            new AuditCommand(),
            ['--composer-lock' => \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'composer_1.7.1_composer.lock']
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertStringContainsString('=== Audit Security Report ===', $output);
        self::assertStringContainsString('This checker can only detect vulnerabilities that are referenced', $output);
        self::assertStringContainsString($this->greenString . ' No known vulnerabilities found', $output);
    }

    public function testAuditCommandWithEmptyComposerLockPath(): void
    {
        $commandTester = $this->executeCommand(
            new AuditCommand(),
            ['--composer-lock' => 'composer_1.7.1_composer.lock']
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertStringContainsString(\trim('=== Audit Security Report ==='), $output);
        self::assertStringContainsString(\trim('Lock file does not exist.'), $output);
    }

    public function testAuditCommandWithError(): void
    {
        $commandTester = $this->executeCommand(
            new AuditCommand(),
            ['--composer-lock' => \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.lock']
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertStringContainsString('=== Audit Security Report ===', $output);
        self::assertStringContainsString('This checker can only detect vulnerabilities that are referenced', $output);
        self::assertStringContainsString('symfony/symfony (v2.5.2)', $output);
        self::assertStringContainsString($this->redString . ' 2 vulnerabilities found - We recommend you to check the related security advisories and upgrade these dependencies.', $output);
        self::assertSame(1, $commandTester->getStatusCode());
    }

    public function testAuditCommandWithErrorAndZeroExitCode(): void
    {
        $commandTester = $this->executeCommand(
            new AuditCommand(),
            [
                '--composer-lock' => \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.lock',
                '--disable-exit' => null,
            ]
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertStringContainsString('=== Audit Security Report ===', $output);
        self::assertStringContainsString('This checker can only detect vulnerabilities that are referenced', $output);
        self::assertStringContainsString('symfony/symfony (v2.5.2)', $output);
        self::assertStringContainsString($this->redString . ' 2 vulnerabilities found - We recommend you to check the related security advisories and upgrade these dependencies.', $output);
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testAuditCommandWithErrorZeroExitCodeAndOneVulnerability(): void
    {
        $commandTester = $this->executeCommand(
            new AuditCommand(),
            [
                '--composer-lock' => \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'pygmentize_1.1_composer.lock',
                '--disable-exit' => null,
            ]
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertStringContainsString('=== Audit Security Report ===', $output);
        self::assertStringContainsString('This checker can only detect vulnerabilities that are referenced', $output);
        self::assertStringContainsString('3f/pygmentize (1.1)', $output);
        self::assertStringContainsString($this->redString . ' 1 vulnerability found - We recommend you to check the related security advisories and upgrade these dependencies.', $output);
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testAuditCommandWithErrorAndJsonFormat(): void
    {
        $commandTester = $this->executeCommand(
            new AuditCommand(),
            [
                '--composer-lock' => \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.lock',
                '--format' => 'json',
                '--timeout' => '20',
            ]
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertJson(\strstr(\substr($output, 0, \strrpos($output, '}') + 1), '{'));
        self::assertStringContainsString('=== Audit Security Report ===', $output);
        self::assertStringContainsString('This checker can only detect vulnerabilities that are referenced', $output);
        self::assertStringContainsString($this->redString . ' 2 vulnerabilities found - We recommend you to check the related security advisories and upgrade these dependencies.', $output);
    }

    public function testAuditCommandWithErrorAndSimpleFormat(): void
    {
        $commandTester = $this->executeCommand(
            new AuditCommand(),
            [
                '--composer-lock' => \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.lock',
                '--format' => 'simple',
            ]
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertStringContainsString('=== Audit Security Report ===', $output);
        self::assertStringContainsString(\trim('symfony/symfony (v2.5.2)
------------------------
'), $output);
        self::assertStringContainsString('This checker can only detect vulnerabilities that are referenced', $output);
        self::assertStringContainsString($this->redString . ' 2 vulnerabilities found - We recommend you to check the related security advisories and upgrade these dependencies.', $output);
    }

    /**
     * @param \Symfony\Component\Console\Command\Command $command
     * @param array                                      $input
     * @param array                                      $options
     *
     * @return \Symfony\Component\Console\Tester\CommandTester
     */
    protected function executeCommand(Command $command, array $input = [], array $options = []): CommandTester
    {
        $this->application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()] + $input, $options);

        return $commandTester;
    }
}
