<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Command;

use Composer\Command\BaseCommand;
use Composer\Factory;
use Composer\IO\NullIO;
use Narrowspark\Automatic\Security\Audit;
use Narrowspark\Automatic\Security\Command\Formatter\JsonFormatter;
use Narrowspark\Automatic\Security\Command\Formatter\SimpleFormatter;
use Narrowspark\Automatic\Security\Command\Formatter\TextFormatter;
use Narrowspark\Automatic\Security\Contract\Exception\RuntimeException;
use Narrowspark\Automatic\Security\Downloader\ComposerDownloader;
use Narrowspark\Automatic\Security\Downloader\CurlDownloader;
use Narrowspark\Automatic\Security\Util;
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
        if (\extension_loaded('curl')) {
            $downloader = new CurlDownloader();
        } else {
            $downloader = new ComposerDownloader();
        }

        /** @var null|string $timeout */
        $timeout = $input->getOption('timeout');

        if ($timeout !== null) {
            $downloader->setTimeout((int) $timeout);
        }

        $config = Factory::createConfig(new NullIO());
        $audit  = new Audit(\rtrim($config->get('vendor-dir'), '/'), $downloader);

        /** @var null|string $composerFile */
        $composerFile = $input->getOption('composer-lock');

        if ($composerFile === null) {
            $composerFile = Util::getComposerLockFile();
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

        $message = 'This checker can only detect vulnerabilities that are referenced in the SensioLabs security advisories database.';

        if (\method_exists($output, 'comment')) {
            $output->comment($message);
        } else {
            $output->writeln(\sprintf('<comment>%s</>', $message));
        }

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
