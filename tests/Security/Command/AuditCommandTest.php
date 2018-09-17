<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Test;

use Composer\Console\Application;
use Narrowspark\Automatic\Security\Command\AuditCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
final class AuditCommandTest extends TestCase
{
    /**
     * @var \Composer\Console\Application
     */
    private $application;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->application = new Application();
    }

    public function testAuditCommand(): void
    {
        \putenv('COMPOSER=' . \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'composer_1.7.1_composer.lock');

        $commandTester = $this->executeCommand(new AuditCommand());

        static::assertContains('[+] No known vulnerabilities found', \trim($commandTester->getDisplay(true)));

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    public function testAuditCommandWithComposerLockOption(): void
    {
        $commandTester = $this->executeCommand(
            new AuditCommand(),
            ['--composer-lock' => \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'composer_1.7.1_composer.lock']
        );

        $output = \trim($commandTester->getDisplay(true));

        static::assertContains('=== Audit Security Report ===', $output);
        static::assertContains('This checker can only detect vulnerabilities that are referenced', $output);
        static::assertContains('[+] No known vulnerabilities found', $output);
    }

    public function testAuditCommandWithEmptyComposerLockPath(): void
    {
        $commandTester = $this->executeCommand(
            new AuditCommand(),
            ['--composer-lock' => 'composer_1.7.1_composer.lock']
        );

        $output = \trim($commandTester->getDisplay(true));

        static::assertContains(\trim('=== Audit Security Report ==='), $output);
        static::assertContains(\trim('Lock file does not exist.'), $output);
    }

    public function testAuditCommandWithError(): void
    {
        $commandTester = $this->executeCommand(
            new AuditCommand(),
            ['--composer-lock' => \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.lock']
        );

        $output = \trim($commandTester->getDisplay(true));

        static::assertContains('=== Audit Security Report ===', $output);
        static::assertContains('This checker can only detect vulnerabilities that are referenced', $output);
        static::assertContains('symfony/symfony (v2.5.2)', $output);
        static::assertContains('[!] 1 vulnerability found - We recommend you to check the related security advisories and upgrade these dependencies.', $output);
    }

    public function testAuditCommandWithErrorAndJsonFormat(): void
    {
        $commandTester = $this->executeCommand(
            new AuditCommand(),
            [
                '--composer-lock' => \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.lock',
                '--format'        => 'json',
                '--timeout'       => '20',
            ]
        );

        $output = \trim($commandTester->getDisplay(true));

        $jsonOutput = \str_replace(
            [
                '=== Audit Security Report ===',
                '//',
                'This checker can only detect vulnerabilities that are referenced',
                'in the',
                'SensioLabs security advisories database.',
                '[!] 1 vulnerability found - We recommend you to check the related security advisories and upgrade these dependencies.',
            ],
            '',
            $output
        );

        static::assertJson($jsonOutput);
        static::assertContains('=== Audit Security Report ===', $output);
        static::assertContains('This checker can only detect vulnerabilities that are referenced', $output);
        static::assertContains('[!] 1 vulnerability found - We recommend you to check the related security advisories and upgrade these dependencies.', $output);
    }

    public function testAuditCommandWithErrorAndSimpleFormat(): void
    {
        $commandTester = $this->executeCommand(
            new AuditCommand(),
            [
                '--composer-lock' => \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.lock',
                '--format'        => 'simple',
            ]
        );

        $output = \trim($commandTester->getDisplay(true));

        static::assertContains('=== Audit Security Report ===', $output);
        static::assertContains(\trim('symfony/symfony (v2.5.2)
------------------------
'), $output);
        static::assertContains('This checker can only detect vulnerabilities that are referenced', $output);
        static::assertContains('[!] 1 vulnerability found - We recommend you to check the related security advisories and upgrade these dependencies.', $output);
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

        $reflectionProperty = (new \ReflectionClass($command))->getProperty('defaultName');
        $reflectionProperty->setAccessible(true);

        $command = $this->application->find($reflectionProperty->getValue($command));

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()] + $input, $options);

        return $commandTester;
    }
}
