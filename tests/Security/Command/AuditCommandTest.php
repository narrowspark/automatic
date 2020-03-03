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

use Mockery;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Security\Command\AuditCommand;
use Narrowspark\Automatic\Security\Contract\Audit as AuditContract;
use Narrowspark\Automatic\Security\Contract\Exception\RuntimeException;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Nyholm\NSA;
use PackageVersions\Versions;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Security\Command\AuditCommand
 *
 * @medium
 */
final class AuditCommandTest extends MockeryTestCase
{
    /** @var string */
    private $tmpFolder;

    /** @var \Symfony\Component\Console\Application */
    private $application;

    /** @var string */
    private $greenString;

    /** @var string */
    private $redString;

    /** @var \Narrowspark\Automatic\Security\Command\AuditCommand */
    private $command;

    /** @var \Mockery\MockInterface|\Narrowspark\Automatic\Common\Contract\Container */
    private $containerMock;

    /** @var \Mockery\MockInterface|\Narrowspark\Automatic\Security\Contract\Audit */
    private $auditMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpFolder = __DIR__ . \DIRECTORY_SEPARATOR . 'tmp';
        $this->containerMock = Mockery::mock(ContainerContract::class);

        $this->auditMock = Mockery::mock(AuditContract::class);

        $this->containerMock->shouldReceive('get')
            ->once()
            ->with(AuditContract::class)
            ->andReturn($this->auditMock);

        $this->command = new AuditCommand();

        NSA::setProperty($this->command, 'container', $this->containerMock);

        $this->application = new Application();

        $consoleVersion = \version_compare(Versions::getVersion('symfony/console'), '3.0.0', '<');

        $this->greenString = $consoleVersion ? '' : '[+]';
        $this->redString = $consoleVersion ? '' : '[!]';
    }

    public function testAuditCommand(): void
    {
        $lockFilePath = \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'composer_1.7.1_composer.lock';

        $this->arrangeCheckLock($lockFilePath, [[], []]);

        \putenv('COMPOSER=' . $lockFilePath);

        $commandTester = $this->executeCommand($this->command);

        self::assertStringContainsString($this->greenString . ' No known vulnerabilities found', \trim($commandTester->getDisplay(true)));

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    public function testAuditCommandWithComposerLockOption(): void
    {
        $lockFilePath = \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'composer_1.7.1_composer.lock';

        $this->arrangeCheckLock($lockFilePath, [[], []]);

        $commandTester = $this->executeCommand(
            $this->command,
            ['--composer-lock' => $lockFilePath]
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertStringContainsString('=== Audit Security Report ===', $output);
        self::assertStringContainsString('This checker can only detect vulnerabilities that are referenced', $output);
        self::assertStringContainsString($this->greenString . ' No known vulnerabilities found', $output);
    }

    public function testAuditCommandWithNoDevMode(): void
    {
        $lockFilePath = \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'composer_1.7.1_composer.lock';

        $this->arrangeCheckLock($lockFilePath, [[], []], true);

        \putenv('COMPOSER=' . $lockFilePath);

        $commandTester = $this->executeCommand(
            $this->command,
            ['--no-dev' => true]
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertStringContainsString('=== Audit Security Report ===', $output);
        self::assertStringContainsString('Check is running in no-dev mode. Skipping dev requirements check.', $output);
        self::assertStringContainsString('This checker can only detect vulnerabilities that are referenced', $output);
        self::assertStringContainsString($this->greenString . ' No known vulnerabilities found', $output);

        \putenv('COMPOSER=');
        \putenv('COMPOSER');
    }

    public function testAuditCommandWithEmptyComposerLockPath(): void
    {
        $lockFilePath = 'composer_1.7.1_composer.lock';

        $this->auditMock->shouldReceive('checkLock')
            ->once()
            ->with($lockFilePath)
            ->andThrows(RuntimeException::class, 'Lock file does not exist.');

        $commandTester = $this->executeCommand(
            $this->command,
            ['--composer-lock' => $lockFilePath]
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertStringContainsString(\trim('=== Audit Security Report ==='), $output);
        self::assertStringContainsString(\trim('Lock file does not exist.'), $output);
    }

    public function testAuditCommandWithError(): void
    {
        $lockFilePath = \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.lock';

        $this->arrangeCheckLock($lockFilePath, [$this->arrangeSymfonyAuditDb(), []]);

        $commandTester = $this->executeCommand(
            $this->command,
            ['--composer-lock' => $lockFilePath]
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertStringContainsString('=== Audit Security Report ===', $output);
        self::assertStringContainsString('This checker can only detect vulnerabilities that are referenced', $output);
        self::assertStringContainsString('symfony/symfony (v2.5.2)', $output);
        self::assertStringContainsString($this->redString . ' 1 vulnerability found - We recommend you to check the related security advisories and upgrade these dependencies.', $output);
        self::assertSame(1, $commandTester->getStatusCode());
    }

    public function testAuditCommandWithErrorAndZeroExitCode(): void
    {
        $lockFilePath = \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.lock';

        $this->arrangeCheckLock($lockFilePath, [$this->arrangeSymfonyAuditDb(), []]);

        $commandTester = $this->executeCommand(
            $this->command,
            [
                '--composer-lock' => $lockFilePath,
                '--disable-exit' => null,
            ]
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertStringContainsString('=== Audit Security Report ===', $output);
        self::assertStringContainsString('This checker can only detect vulnerabilities that are referenced', $output);
        self::assertStringContainsString('symfony/symfony (v2.5.2)', $output);
        self::assertStringContainsString($this->redString . ' 1 vulnerability found - We recommend you to check the related security advisories and upgrade these dependencies.', $output);
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testAuditCommandWithErrorZeroExitCodeAndVulnerabilities(): void
    {
        $lockFilePath = \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'pygmentize_1.1_composer.lock';

        $this->arrangeCheckLock($lockFilePath, [
            [
                '3f/pygmentize' => [
                    'version' => '1.1',
                    'advisories' => [
                        'test1' => [
                            'title' => '1',
                            'link' => 'example.de',
                        ],
                    ],
                ],
                'symfony/symfony' => [
                    'version' => 'v2.5.2',
                    'advisories' => [
                        'CVE-2014-4931' => [
                            'title' => 'Code injection in the way Symfony implements translation caching in FrameworkBundle',
                            'link' => 'example.de',
                        ],
                    ],
                ],
            ],
            [],
        ]);

        $commandTester = $this->executeCommand(
            $this->command,
            [
                '--composer-lock' => $lockFilePath,
                '--disable-exit' => null,
            ]
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertStringContainsString('=== Audit Security Report ===', $output);
        self::assertStringContainsString('This checker can only detect vulnerabilities that are referenced', $output);
        self::assertStringContainsString('3f/pygmentize (1.1)', $output);
        self::assertStringContainsString($this->redString . ' 2 vulnerabilities found - We recommend you to check the related security advisories and upgrade these dependencies.', $output);
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testAuditCommandWithErrorArgument(): void
    {
        $lockFilePath = \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.lock';

        $this->arrangeCheckLock($lockFilePath, [$this->arrangeSymfonyAuditDb(), []]);

        $commandTester = $this->executeCommand(
            $this->command,
            [
                '--composer-lock' => $lockFilePath,
                '--format' => 'json',
            ]
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertJson((string) \strstr(\substr($output, 0, (int) \strrpos($output, '}') + 1), '{'));
        self::assertStringContainsString('=== Audit Security Report ===', $output);
        self::assertStringContainsString('This checker can only detect vulnerabilities that are referenced', $output);
        self::assertStringContainsString($this->redString . ' 1 vulnerability found - We recommend you to check the related security advisories and upgrade these dependencies.', $output);
    }

    public function testAuditCommandWithErrorAndSimpleFormat(): void
    {
        $lockFilePath = \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.lock';

        $this->arrangeCheckLock($lockFilePath, [$this->arrangeSymfonyAuditDb(), []]);

        $commandTester = $this->executeCommand(
            $this->command,
            [
                '--composer-lock' => $lockFilePath,
                '--format' => 'simple',
            ]
        );

        $output = \trim($commandTester->getDisplay(true));

        self::assertStringContainsString('=== Audit Security Report ===', $output);
        self::assertStringContainsString(\trim('symfony/symfony (v2.5.2)
------------------------
'), $output);
        self::assertStringContainsString('This checker can only detect vulnerabilities that are referenced', $output);
        self::assertStringContainsString($this->redString . ' 1 vulnerability found - We recommend you to check the related security advisories and upgrade these dependencies.', $output);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods(bool $allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    /**
     * @param array<string, null|bool|string> $input
     * @param array<string, null|bool|string> $options
     */
    private function executeCommand(Command $command, array $input = [], array $options = []): CommandTester
    {
        $this->application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()] + $input, $options);

        return $commandTester;
    }

    /**
     * @param string  $lockFilePath
     * @param array[] $data
     */
    private function arrangeCheckLock(?string $lockFilePath, array $data, bool $devMode = false): void
    {
        if ($devMode) {
            $this->auditMock->shouldReceive('setDevMode')
                ->once()
                ->with($devMode);
        }

        if ($lockFilePath !== null) {
            $this->auditMock->shouldReceive('checkLock')
                ->once()
                ->with($lockFilePath)
                ->andReturn($data);
        }
    }

    /**
     * @return array<string, array<string, array<string, array<string, string>>|string>>
     */
    private function arrangeSymfonyAuditDb(): array
    {
        return [
            'symfony/symfony' => [
                'version' => 'v2.5.2',
                'advisories' => [
                    'CVE-2014-4931' => [
                        'title' => 'Code injection in the way Symfony implements translation caching in FrameworkBundle',
                        'link' => 'example.de',
                    ],
                ],
            ],
        ];
    }
}
