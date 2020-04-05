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

namespace Narrowspark\Automatic\Tests\Fixture;

final class ComposerJsonFactory
{
    public static function createComposerJson(
        string $name,
        array $require = [],
        array $devRequire = [],
        array $autoload = [],
        array $extra = []
    ): string {
        $composerJsonContent = [
            'name' => $name,
            'authors' => [
                [
                    'name' => 'Daniel Bannert',
                    'email' => 'd.bannert@anolilab.de',
                ],
            ],
            'autoload' => $autoload,
            'require' => $require,
            'extra' => $extra,
        ];

        if (\count($devRequire) !== 0) {
            $composerJsonContent['require-dev'] = $devRequire;
        }

        return self::arrayToJson($composerJsonContent);
    }

    public static function createAutomaticComposerJson(
        string $name,
        array $require = [],
        array $devRequire = [],
        array $automaticExtra = []
    ): string {
        $extendedExtra = [
            'automatic' => $automaticExtra,
        ];

        return self::createComposerJson($name, $require, $devRequire, [], $extendedExtra);
    }

    public static function createComposerPluginJson(string $name, array $require = [], array $autoload = []): string
    {
        $composerJsonContent = [
            'name' => $name,
            'pretty-name' => $name . '/' . $name,
            'type' => 'automatic-configurator',
            'description' => 'plugin',
            'authors' => [
                [
                    'name' => 'Daniel Bannert',
                    'email' => 'd.bannert@anolilab.de',
                ],
            ],
            'autoload' => $autoload,
            'require' => $require,
        ];

        return self::arrayToJson($composerJsonContent);
    }

    public static function createComposerScriptJson(string $name, array $script = []): string
    {
        $composerJsonContent = [
            'name' => $name,
            'pretty-name' => $name . '/' . $name,
            'type' => 'automatic-configurator',
            'description' => 'plugin',
            'authors' => [
                [
                    'name' => 'Daniel Bannert',
                    'email' => 'd.bannert@anolilab.de',
                ],
            ],
            'require' => [],
            'scripts' => $script,
        ];

        return self::arrayToJson($composerJsonContent);
    }

    public static function jsonFileToArray(string $jsonFilePath): array
    {
        return \json_decode(\file_get_contents($jsonFilePath), true);
    }

    public static function jsonToArray(string $jsonContent): array
    {
        return \json_decode($jsonContent, true);
    }

    public static function arrayToJson(array $jsonData): string
    {
        return \json_encode($jsonData, \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
    }
}
