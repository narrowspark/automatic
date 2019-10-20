<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Common\ScriptExtender;

use Composer\Util\ProcessExecutor;
use Narrowspark\Automatic\Common\Contract\Exception\RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;

class PhpScriptExtender extends AbstractScriptExtender
{
    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'php-script';
    }

    /**
     * {@inheritdoc}
     */
    public function expand(string $cmd): string
    {
        $phpFinder = new PhpExecutableFinder();
        // @codeCoverageIgnoreStart
        if (($php = $phpFinder->find(false)) === false) {
            throw new RuntimeException('The PHP executable could not be found, add it to your PATH and try again.');
        }
        // @codeCoverageIgnoreEnd
        $arguments = $phpFinder->findArguments();

        if (($env = \getenv('COMPOSER_ORIGINAL_INIS')) !== false) {
            $paths = \explode(\PATH_SEPARATOR, (string) $env);
            $ini = \array_shift($paths);
        } else {
            $ini = \php_ini_loaded_file();
        }

        if (\is_string($ini)) {
            $arguments[] = '--php-ini=' . $ini;
        }

        $phpArgs = \implode(' ', \array_map([ProcessExecutor::class, 'escape'], $arguments));

        return ProcessExecutor::escape((string) $php) . ($phpArgs !== '' ? ' ' . $phpArgs : '') . ' ' . $cmd;
    }
}
