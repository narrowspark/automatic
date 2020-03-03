<?php

declare(strict_types=1);

use Ergebnis\License;
use Narrowspark\CS\Config\Config;

$license = static function ($path) {
    return License\Type\MIT::markdown(
        $path . '/LICENSE.md',
        License\Range::since(
            License\Year::fromString('2018'),
            new \DateTimeZone('UTC')
        ),
        License\Holder::fromString('Daniel Bannert'),
        License\Url::fromString('https://github.com/narrowspark/automatic')
    );
};

$mainLicense = $license(__DIR__);
$mainLicense->save();

$license(__DIR__ . '/src/Common')->save();
$license(__DIR__ . '/src/Prefetcher')->save();
$license(__DIR__ . '/src/Security')->save();

$config = new Config($mainLicense->header(), [
    'native_function_invocation' => [
        'exclude' => [
            'getcwd',
            'extension_loaded',
        ],
    ],
    'final_class' => false,
    'final_public_method_for_abstract_class' => false,
    // @todo waiting for php-cs-fixer 2.16.2
    'global_namespace_import' => [
        'import_classes' => true,
        'import_constants' => false,
        'import_functions' => false,
    ]
]);

$config->getFinder()
    ->files()
    ->in(__DIR__)
    ->exclude('build')
    ->exclude('vendor')
    ->exclude('src/Prefetcher/Common')
    ->notPath('src/Prefetcher/alias.php')
    ->exclude('src/Security/Common')
    ->notPath('src/Security/alias.php')
    ->notPath('tests/Automatic/Configurator/EnvConfiguratorTest.php')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config->setCacheFile(__DIR__ . '/.build/php-cs-fixer/.php_cs.cache');

return $config;
