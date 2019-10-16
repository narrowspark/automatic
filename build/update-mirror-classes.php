#!/usr/bin/env php
<?php

$rootDir = dirname(__DIR__, 1);

require $rootDir . '/vendor/symfony/filesystem/Exception/ExceptionInterface.php';
require $rootDir . '/vendor/symfony/filesystem/Exception/IOExceptionInterface.php';
require $rootDir . '/vendor/symfony/filesystem/Exception/IOException.php';
require $rootDir . '/vendor/symfony/filesystem/Filesystem.php';

use Symfony\Component\Filesystem\Filesystem;

$defaultCommonSettings = [
    'path' => 'Common',
    'namespace' => 'Automatic\\Common',
];

$mirrorList = [
    'src/Common/Contract/Exception/Exception.php' => $defaultCommonSettings,
    'src/Common/Contract/Exception/InvalidArgumentException.php' => $defaultCommonSettings,
    'src/Common/Contract/Container.php' => $defaultCommonSettings,
    'src/Common/AbstractContainer.php' => $defaultCommonSettings,
    'src/Common/Contract/Resettable.php' => $defaultCommonSettings,
    'src/Common/Traits/GetGenericPropertyReaderTrait.php' => $defaultCommonSettings,
    'src/Common/Traits/GetComposerVersionTrait.php' => $defaultCommonSettings,
    'src/Common/Contract/Exception/RuntimeException.php' => $defaultCommonSettings,
];

$outputConfigs = [
    'Prefetcher' . DIRECTORY_SEPARATOR . 'Common' => 'Automatic\\Prefetcher\\Common',
];

$fs = new Filesystem();

$comment = <<<STRING
/**
 * This file is automatically generated, dont change this file, otherwise the changes are lost after the next mirror update.
 *
 * @codeCoverageIgnore
 * @internal
 */
STRING;

foreach ($outputConfigs as $path => $namspace) {
    $fs->remove($rootDir . DIRECTORY_SEPARATOR . $path);
}

foreach ($mirrorList as $path => $settings) {
    foreach ($outputConfigs as $outputPath => $namespace) {
        $preparedOutputPath = str_replace("/{$settings['path']}/", "/{$outputPath}/", $path);

        $fs->copy($path, $preparedOutputPath, true);

        $content = file_get_contents($preparedOutputPath);
        $content = str_replace(["\nclass", "\nabstract class", "\ninterface"], ["\n{$comment}\nclass", "\n{$comment}\nabstract class", "\n{$comment}\ninterface"], $content);

        $fs->dumpFile($preparedOutputPath, str_replace($settings['namespace'], $namespace, $content));

        echo "Dumped {$preparedOutputPath}.\n";
    }
}
