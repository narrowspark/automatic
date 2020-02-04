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

namespace Narrowspark\Automatic\Test\AutoReview;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 * @coversNothing
 */
final class MirrorClassTest extends TestCase
{
    public function testMirrorFiles(): void
    {
        $rootPath = \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR;
        $comment = MirrorSettings::COMMENT_STRING;

        foreach (MirrorSettings::MIRROR_LIST as $list) {
            $outputSettings = $list['output'];

            foreach ($list['mirror_list'] as $path => $settings) {
                $content = \file_get_contents($rootPath . $path);
                $content = \str_replace(
                    [
                        "\nclass",
                        "\nabstract class",
                        "\ninterface",
                    ],
                    [
                        "\n{$comment}\nclass",
                        "\n{$comment}\nabstract class",
                        "\n{$comment}\ninterface",
                    ],
                    $content
                );
                $content = \str_replace($settings['namespace'], $outputSettings['namespace'], $content);

                $outputPath = $outputSettings['path'] . $settings['path'];

                $mirrorPath = \str_replace("/{$settings['path']}/", "/{$outputPath}/", $rootPath . $path);

                self::assertSame($content, \file_get_contents($mirrorPath), $mirrorPath);
            }
        }
    }
}
