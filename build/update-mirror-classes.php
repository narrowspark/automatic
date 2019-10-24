#!/usr/bin/env php
<?php

$rootDir = \dirname(__DIR__, 1);

require $rootDir . '/vendor/symfony/filesystem/Exception/ExceptionInterface.php';

require $rootDir . '/vendor/symfony/filesystem/Exception/IOExceptionInterface.php';

require $rootDir . '/vendor/symfony/filesystem/Exception/IOException.php';

require $rootDir . '/vendor/symfony/filesystem/Filesystem.php';

require $rootDir . '/tests/Internal/MirrorSettings.php';

use Narrowspark\Automatic\Test\Internal\MirrorSettings;
use Symfony\Component\Filesystem\Filesystem;

$fs = new Filesystem();

foreach (MirrorSettings::OUTPUT_LIST as $path => $namspace) {
    $fs->remove($rootDir . \DIRECTORY_SEPARATOR . $path);
}

$comment = MirrorSettings::COMMENT_STRING;

$regex = '/namespace ([a-zA-z]*)/s';
$aliasList = [];

foreach (MirrorSettings::MIRROR_LIST as $path => $settings) {
    foreach (MirrorSettings::OUTPUT_LIST as $outputPath => $namespace) {

        $preparedOutputPath = \str_replace("/{$settings['path']}/", '/' . $outputPath.$settings['path'] . '/', $path);

        $fs->copy($path, $preparedOutputPath, true);

        $content = \file_get_contents($preparedOutputPath);
        $content = \str_replace(["\nclass", "\nabstract class", "\ninterface"], ["\n{$comment}\nclass", "\n{$comment}\nabstract class", "\n{$comment}\ninterface"], $content);

        $mirrorContent = \str_replace($settings['namespace'], $namespace, $content);

        \preg_match($regex, $content, $matches, \PREG_OFFSET_CAPTURE, 0);
        \preg_match($regex, $mirrorContent, $mirrorMatches, \PREG_OFFSET_CAPTURE, 0);
        \preg_match('/(abstract class |final class |class |interface |trait )([A-z]*)/s', $content, $classMatches, \PREG_OFFSET_CAPTURE, 0);

        if (! array_key_exists($outputPath, $aliasList)) {
            $aliasList[$outputPath] = [];
        }

        $aliasList[$outputPath][] = '\class_alias(' . $mirrorMatches[1][0] . '\\' . $classMatches[2][0] . '::class, ' . $matches[1][0] . '\\' . $classMatches[2][0] . '::class);' . "\n";

        $fs->dumpFile($preparedOutputPath, $mirrorContent);

        echo "Dumped {$preparedOutputPath}.\n";

        $mirrorContent = $content = $matches = $mirrorMatches = null;
    }
}

echo "\n";

$header = <<<'EOF'
/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
EOF;

foreach ($aliasList as $output => $aliases) {
    $preparedOutputPath = 'src/'.rtrim($output, '/').'/alias.php';

    $fs->dumpFile($rootDir . DIRECTORY_SEPARATOR . $preparedOutputPath, "<?php\n\ndeclare(strict_types=1);\n\n" . $header . "\n\n" . implode('', $aliases));

    echo "Dumped {$preparedOutputPath}.\n";
}
