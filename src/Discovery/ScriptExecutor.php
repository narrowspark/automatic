<?php
declare(strict_types=1);
namespace Narrowspark\Discovery;

use Composer\Composer;
use Composer\EventDispatcher\ScriptExecutionException;
use Composer\IO\IOInterface;
use Composer\Semver\Constraint\EmptyConstraint;
use Composer\Util\ProcessExecutor;
use Narrowspark\Discovery\Common\Traits\ExpandTargetDirTrait;
use Narrowspark\Discovery\Exception\InvalidArgumentException;
use Narrowspark\Discovery\Exception\RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Process\PhpExecutableFinder;
use Viserio\Component\Console\Application;

class ScriptExecutor
{
    use ExpandTargetDirTrait;

    /**
     * A Composer instance.
     *
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * A instance of a IOInterface.
     *
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * A ProcessExecutor instance.
     *
     * @var \Composer\Util\ProcessExecutor
     */
    private $executor;

    /**
     * A array of root project options.
     *
     * @var array
     */
    private $options;

    /**
     * Create a new ScriptExecutor instance.
     *
     * @param \Composer\Composer             $composer
     * @param \Composer\IO\IOInterface       $io
     * @param array                          $options
     * @param \Composer\Util\ProcessExecutor $executor
     */
    public function __construct(Composer $composer, IOInterface $io, array $options, ProcessExecutor $executor)
    {
        $this->composer = $composer;
        $this->io       = $io;
        $this->options  = $options;
        $this->executor = $executor;
    }

    /**
     * @param string $type
     * @param string $cmd
     *
     * @throws \Composer\EventDispatcher\ScriptExecutionException if the executed command returns a non-0 exit code
     *
     * @return void
     */
    public function execute(string $type, string $cmd): void
    {
        $parsedCmd   = self::expandTargetDir($this->options, $cmd);
        $expandedCmd = $this->expandCmd($type, $parsedCmd);

        if ($expandedCmd === null) {
            return;
        }

        $cmdOutput = new StreamOutput(
            \fopen('php://memory', 'rw'),
            OutputInterface::VERBOSITY_VERBOSE,
            $this->io->isDecorated()
        );

        $outputHandler = function ($type, $buffer) use ($cmdOutput): void {
            $cmdOutput->write($buffer, false, OutputInterface::OUTPUT_RAW);
        };

        $this->io->writeError(\sprintf('Executing script %s', $parsedCmd), $this->io->isVerbose());

        $exitCode = $this->executor->execute($expandedCmd, $outputHandler);

        $code = $exitCode === 0 ? ' <info>[OK]</info>' : ' <error>[KO]</error>';

        if ($this->io->isVerbose()) {
            $this->io->writeError(\sprintf('Executed script %s %s', $cmd, $code));
        } else {
            $this->io->writeError($code);
        }

        if ($exitCode !== 0) {
            $this->io->writeError(' <error>[KO]</error>');
            $this->io->writeError(\sprintf('<error>Script %s returned with error code %s</error>', $cmd, $exitCode));

            \fseek($cmdOutput->getStream(), 0);

            foreach (\explode("\n", \stream_get_contents($cmdOutput->getStream())) as $line) {
                $this->io->writeError('!!  ' . $line);
            }

            throw new ScriptExecutionException($cmd, $exitCode);
        }
    }

    /**
     * @param string $type
     * @param string $cmd
     *
     * @throws \Narrowspark\Discovery\Exception\InvalidArgumentException
     *
     * @return null|string
     */
    private function expandCmd(string $type, string $cmd): ?string
    {
        switch ($type) {
            case 'cerebro-cmd':
                return $this->expandCerebroCmd($cmd);
            case 'php-script':
                return $this->expandPhpScript($cmd);
            case 'script':
                return $cmd;
            default:
                throw new InvalidArgumentException(\sprintf('Command type "%s" is not valid.', $type));
        }
    }

    /**
     * @param string $cmd
     *
     * @return null|string
     */
    private function expandCerebroCmd(string $cmd): ?string
    {
        $repo = $this->composer->getRepositoryManager()->getLocalRepository();

        if (! $repo->findPackage('viserio/console', new EmptyConstraint())) {
            $this->io->writeError(\sprintf('<warning>Skipping "%s" (needs viserio/console to run).</warning>', $cmd));

            return null;
        }

        $console = Application::cerebroBinary();

        if ($this->io->isDecorated()) {
            $console .= ' --ansi';
        }

        return $this->expandPhpScript($console . ' ' . $cmd);
    }

    /**
     * @param string $cmd
     *
     * @throws \Narrowspark\Discovery\Exception\RuntimeException
     *
     * @return string
     */
    private function expandPhpScript(string $cmd): string
    {
        $phpFinder = new PhpExecutableFinder();

        if (! $php = $phpFinder->find(false)) {
            throw new RuntimeException('The PHP executable could not be found, add it to your PATH and try again.');
        }

        $arguments = $phpFinder->findArguments();

        if ($env = (string) (\getenv('COMPOSER_ORIGINAL_INIS'))) {
            $paths = \explode(\PATH_SEPARATOR, $env);
            $ini   = \array_shift($paths);
        } else {
            $ini = \php_ini_loaded_file();
        }

        if ($ini) {
            $arguments[] = '--php-ini=' . $ini;
        }

        $phpArgs = \implode(' ', \array_map('escapeshellarg', $arguments));

        return \escapeshellarg($php) . ($phpArgs ? ' ' . $phpArgs : '') . ' ' . $cmd;
    }
}
