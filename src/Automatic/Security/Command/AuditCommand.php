<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AuditCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('audit')
            ->setDefinition(array(
                new InputOption('format', '', InputOption::VALUE_REQUIRED, 'The output format', 'text'),
                new InputOption('endpoint', '', InputOption::VALUE_REQUIRED, 'The security checker server URL'),
                new InputOption('timeout', '', InputOption::VALUE_REQUIRED, 'The HTTP timeout in seconds'),
            ))
            ->setDescription('Checks security issues in your project dependencies')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command looks for security issues in the
project dependencies:
<info>%command.full_name%</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

    }
}
