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
use const DIRECTORY_SEPARATOR;
use function dirname;
use function file_get_contents;
use function str_replace;

/**
 * @internal
 *
 * @small
 */
final class MirrorClassTest extends TestCase
{
    public function testMirrorFiles(): void
    {
        $rootPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
        $comment = MirrorSettings::COMMENT_STRING;

        foreach (MirrorSettings::MIRROR_LIST as $list) {
            $outputSettings = $list['output'];

            foreach ($list['mirror_list'] as $path => $settings) {
                $content = file_get_contents($rootPath . $path);
                $content = str_replace(["\nclass", "\nabstract class", "\ninterface"], ["\n{$comment}\nclass", "\n{$comment}\nabstract class", "\n{$comment}\ninterface"], $content);
                $content = str_replace($settings['namespace'], $outputSettings['namespace'], $content);

                $outputPath = $outputSettings['path'] . $settings['path'];

                $mirrorPath = str_replace("/{$settings['path']}/", "/{$outputPath}/", $rootPath . $path);

                self::assertSame($content, file_get_contents($mirrorPath), $mirrorPath);
            }
        }
    }
}
