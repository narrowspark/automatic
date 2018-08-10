<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\ScriptExtender;

use Narrowspark\Automatic\Common\Contract\Exception\RuntimeException;
use Narrowspark\Automatic\Common\Contract\ScriptExtender as ScriptExtenderContract;
use Symfony\Component\Process\PhpExecutableFinder;

final class PhpScriptExtender implements ScriptExtenderContract
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

        if (! $php = $phpFinder->find(false)) {
            throw new RuntimeException('The PHP executable could not be found, add it to your PATH and try again.');
        }

        $arguments = $phpFinder->findArguments();

        if ($env = (string) \getenv('COMPOSER_ORIGINAL_INIS')) {
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
