<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Command;

use Composer\Command\BaseCommand;
use Composer\Factory;
use Narrowspark\Automatic\Common\Contract\Exception\RuntimeException;
use Narrowspark\Automatic\Common\Util;
use Narrowspark\Automatic\Security\Audit;
use Narrowspark\Automatic\Security\Command\Formatter\JsonFormatter;
use Narrowspark\Automatic\Security\Command\Formatter\SimpleFormatter;
use Narrowspark\Automatic\Security\Command\Formatter\TextFormatter;
use Narrowspark\Automatic\Security\Downloader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AuditCommand extends BaseCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'audit';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('audit')
            ->setDefinition([
                new InputOption('composer-lock', '', InputOption::VALUE_REQUIRED, 'Path to a composer.lock'),
                new InputOption('format', '', InputOption::VALUE_REQUIRED, 'The output format', 'txt'),
                new InputOption('timeout', '', InputOption::VALUE_REQUIRED, 'The HTTP timeout in seconds'),
            ])
            ->setDescription('Checks security issues in your project dependencies')
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> command looks for security issues in the
project dependencies:
<info>%command.full_name%</info>
EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer   = $this->getComposer();
        $downloader = new Downloader();
        $extra      = $composer->getPackage()->getExtra();

        if (isset($extra[Util::COMPOSER_EXTRA_KEY]['audit']['timeout'])) {
            $downloader->setTimeout($extra[Util::COMPOSER_EXTRA_KEY]['audit']['timeout']);
        } elseif (($timeout = $input->getOption('timeout')) !== null) {
            $downloader->setTimeout($timeout);
        }

        $audit = new Audit(\rtrim($composer->getConfig()->get('vendor-dir'), '/'), $downloader);

        if ($input->getOption('composer-lock') !== null) {
            $composerFile = $input->getOption('composer-lock');
        } else {
            $composerFile = \str_replace('json', 'lock', Factory::getComposerFile());
        }

        $output = new SymfonyStyle($input, $output);
        $output->writeln('=== Audit Security Report ===');

        try {
            [$vulnerabilities, $messages] = $audit->checkLock($composerFile);
        } catch (RuntimeException $exception) {
            /** @var \Symfony\Component\Console\Helper\FormatterHelper $formatter */
            $formatter = $this->getHelperSet()->get('formatter');

            $output->writeln($formatter->formatBlock($exception->getMessage(), 'error', true));

            return 1;
        }
        $output->comment('This checker can only detect vulnerabilities that are referenced in the SensioLabs security advisories database.');

        if (\count($messages) !== 0) {
            $output->note('Please report this found messages to https://github.com/narrowspark/security-advisories.');

            foreach ($messages as $key => $message) {
                $output->writeln($key . ': ' . $message);
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
            $output->writeln('<error>[!]</> ' . \sprintf('%s vulnerabilit%s found - ', $count, $count === 1 ? 'y' : 'ies') .
                'We recommend you to check the related security advisories and upgrade these dependencies.');

            return 1;
        }

        $output->writeln('<fg=black;bg=green>[+]</> No known vulnerabilities found');

        return 0;
    }
}
