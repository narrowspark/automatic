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

namespace Narrowspark\Automatic;

use Composer\Composer;
use Composer\EventDispatcher\ScriptExecutionException;
use Composer\IO\IOInterface;
use Composer\Util\ProcessExecutor;
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\Common\Contract\ScriptExtender as ScriptExtenderContract;
use Narrowspark\Automatic\Common\Traits\ExpandTargetDirTrait;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

final class ScriptExecutor
{
    use ExpandTargetDirTrait;

    /** @var string */
    public const TYPE = 'script-extenders';

    /**
     * A Composer instance.
     *
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * A instance of a IOInterface.
     *
     * @var \Composer\IO\IOInterface
     */
    private $io;

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
     * A list of the registered extenders.
     *
     * @var string[]
     */
    private $extenders = [];

    /**
     * Create a new ScriptExtender instance.
     */
    public function __construct(Composer $composer, IOInterface $io, ProcessExecutor $executor, array $options)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->executor = $executor;
        $this->options = $options;
    }

    /**
     * Register a cmd extender.
     */
    public function add(string $name, string $extender): void
    {
        if (isset($this->extenders[$name])) {
            throw new InvalidArgumentException(\sprintf('Script executor with the name [%s] already exists.', $name));
        }

        if (! \is_subclass_of($extender, ScriptExtenderContract::class)) {
            throw new InvalidArgumentException(\sprintf('The class [%s] must implement the interface [%s].', $extender, ScriptExtenderContract::class));
        }

        $this->extenders[$name] = $extender;
    }

    /**
     * @throws \Composer\EventDispatcher\ScriptExecutionException if the executed command returns a non-0 exit code
     */
    public function execute(string $type, string $cmd): void
    {
        if (! isset($this->extenders[$type])) {
            return;
        }

        $parsedCmd = self::expandTargetDir($this->options, $cmd);
        $isVerbose = $this->io->isVerbose();

        $cmdOutput = new StreamOutput(
            \fopen('php://temp', 'rwb'),
            OutputInterface::VERBOSITY_VERBOSE,
            $this->io->isDecorated()
        );

        // @codeCoverageIgnoreStart
        $outputHandler = static function (?string $type, string $buffer) use ($cmdOutput): void {
            $cmdOutput->write($buffer, false, OutputInterface::OUTPUT_RAW);
        };
        // @codeCoverageIgnoreEnd

        $this->io->writeError(\sprintf('Executing script [%s]', $parsedCmd), $isVerbose);

        /** @var \Narrowspark\Automatic\Common\Contract\ScriptExtender $extender */
        $extender = new $this->extenders[$type]($this->composer, $this->io, $this->options);

        $exitCode = $this->executor->execute($extender->expand($parsedCmd), $outputHandler);

        if ($isVerbose) {
            $this->io->writeError(\sprintf('Executed script [%s] %s', $cmd, $exitCode === 0 ? '<info>[OK]</info>' : '<error>[KO]</error>'));
        }

        if ($exitCode !== 0) {
            if (! $isVerbose) {
                $this->io->writeError('<error>[KO]</error>');
            }

            $this->io->writeError(\sprintf('<error>Script [%s] returned with error code %s</error>', $cmd, $exitCode));

            \fseek($cmdOutput->getStream(), 0);

            foreach (\explode("\n", (string) \stream_get_contents($cmdOutput->getStream())) as $line) {
                $this->io->writeError('!!  ' . $line);
            }

            throw new ScriptExecutionException($cmd, $exitCode);
        }

        if (! $isVerbose) {
            $this->io->writeError('<info>[OK]</info>');
        }
    }
}
