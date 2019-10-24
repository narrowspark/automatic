<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\Automatic\Test\Internal;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
final class MirrorClassTest extends TestCase
{
    public function testMirrorFiles(): void
    {
        $rootPath = \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR;

        foreach (MirrorSettings::MIRROR_LIST as $path => $settings) {
            foreach (MirrorSettings::OUTPUT_LIST as $outputPath => $namespace) {
                $comment = MirrorSettings::COMMENT_STRING;

                $content = \file_get_contents($rootPath . $path);
                $content = \str_replace(["\nclass", "\nabstract class", "\ninterface"], ["\n{$comment}\nclass", "\n{$comment}\nabstract class", "\n{$comment}\ninterface"], $content);
                $content = \str_replace($settings['namespace'], $namespace, $content);

                $outputPath = $outputPath . $settings['path'];

                $mirrorPath = \str_replace("/{$settings['path']}/", "/{$outputPath}/", $rootPath . $path);

                self::assertSame($content, \file_get_contents($mirrorPath), $mirrorPath);
            }
        }
    }
}
