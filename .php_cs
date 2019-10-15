<?php
use Narrowspark\CS\Config\Config;

$config = new Config(null, [
    'native_function_invocation' => [
        'exclude' => [
            'getcwd',
        ],
    ],
    'comment_to_phpdoc' => false,
    'final_class' => false,
    'PhpCsFixerCustomFixers/no_commented_out_code' => false,
]);
$config->getFinder()
    ->files()
    ->in(__DIR__)
    ->exclude('build')
    ->exclude('vendor')
    ->exclude('src/Prefetcher/Common')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$cacheDir = getenv('TRAVIS') ? getenv('HOME') . '/.php-cs-fixer' : __DIR__;

$config->setCacheFile($cacheDir . '/.php_cs.cache');

return $config;
