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

namespace Narrowspark\Automatic\Security\Command;

use Composer\Command\BaseCommand;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Common\Util;
use Narrowspark\Automatic\Security\Command\Formatter\JsonFormatter;
use Narrowspark\Automatic\Security\Command\Formatter\SimpleFormatter;
use Narrowspark\Automatic\Security\Command\Formatter\TextFormatter;
use Narrowspark\Automatic\Security\Container;
use Narrowspark\Automatic\Security\Contract\Audit as AuditContract;
use Narrowspark\Automatic\Security\Contract\Exception\RuntimeException;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class AuditCommand extends BaseCommand
{
    /** @var string */
    protected static $defaultName = 'audit';

    /**
     * A Container instance.
     *
     * @var \Narrowspark\Automatic\Common\Contract\Container
     */
    protected $container;

    /**
     * Get the Container instance.
     */
    public function getContainer(): ContainerContract
    {
        if ($this->container === null) {
            $this->container = new Container($this->getComposer(), $this->getIO());
        }

        return $this->container;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('audit')
            ->setDefinition([
                new InputOption('composer-lock', null, InputOption::VALUE_REQUIRED, 'Path to a composer.lock'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format', 'txt'),
                new InputOption('no-dev', null, InputOption::VALUE_NONE, 'Disables the dev mode.'),
                new InputOption('disable-exit', null, InputOption::VALUE_NONE, 'Only shows which vulnerabilities was found or not (without exit code)'),
            ])
            ->setDescription('Checks security issues in your project dependencies')
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> command looks for security issues in the project dependencies.
EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var \Narrowspark\Automatic\Security\Contract\Audit $audit */
        $audit = $this->getContainer()->get(AuditContract::class);

        $isNotDevMode = true;

        if ((bool) $input->getOption('no-dev')) {
            $isNotDevMode = false;

            $audit->setDevMode(true);
        }

        /** @var null|string $composerFile */
        $composerFile = $input->getOption('composer-lock');

        if ($composerFile === null) {
            $composerFile = Util::getComposerLockFile();
        }

        $output = new SymfonyStyle($input, $output);
        $output->writeln('=== Audit Security Report ===');

        if (! $isNotDevMode) {
            $message = 'Check is running in no-dev mode. Skipping dev requirements check.';

            if (\method_exists($output, 'comment')) {
                $output->comment($message);
            } else {
                $output->writeln(\sprintf('<comment>%s</>', $message));
            }
        }

        $errorExitCode = 1;

        if ((bool) $input->getOption('disable-exit') !== false) {
            $errorExitCode = 0;
        }

        try {
            [$vulnerabilities, $messages] = $audit->checkLock($composerFile);
        } catch (RuntimeException $exception) {
            /** @var HelperSet $helperSet */
            $helperSet = $this->getHelperSet();

            /** @var \Symfony\Component\Console\Helper\FormatterHelper $formatter */
            $formatter = $helperSet->get('formatter');

            $output->writeln($formatter->formatBlock($exception->getMessage(), 'error', true));

            return $errorExitCode;
        }

        $message = 'This checker can only detect vulnerabilities that are referenced in the SensioLabs security or the Github security advisories database.';

        if (\method_exists($output, 'comment')) {
            $output->comment($message);
        } else {
            $output->writeln(\sprintf('<comment>%s</>', $message));
        }

        if (\count($messages) !== 0) {
            $output->note('Please report this found messages to https://github.com/narrowspark/security-advisories.');

            foreach ($messages as $key => $msg) {
                $output->writeln($key . ': ' . $msg);
            }
        }

        $count = \count($vulnerabilities);

        if (\count($vulnerabilities) !== 0) {
            switch ($input->getOption('format')) {
                case 'json':
                    $formatter = new JsonFormatter();

                    break;
                case 'simple':
                    $formatter = new SimpleFormatter();

                    break;
                case 'txt':
                default:
                    $formatter = new TextFormatter();
            }

            $formatter->displayResults($output, $vulnerabilities);

            $output->writeln('<error>[!]</> ' . \sprintf('%s vulnerabilit%s found - ', $count, $count === 1 ? 'y' : 'ies')
                . 'We recommend you to check the related security advisories and upgrade these dependencies.');

            return $errorExitCode;
        }

        $output->writeln('<fg=black;bg=green>[+]</> No known vulnerabilities found');

        return 0;
    }
}
