<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Fixtures;

class ComposerJsonFactory
{
    /**
     * @param string $name
     * @param array  $require
     * @param array  $devRequire
     * @param array  $autoload
     * @param array  $extra
     *
     * @return string
     */
    public static function createComposerJson(
        string $name,
        array $require = [],
        array $devRequire = [],
        array $autoload = [],
        array $extra = []
    ): string {
        $composerJsonContent = [
            'name'    => $name,
            'authors' => [
                [
                    'name'  => 'Daniel Bannert',
                    'email' => 'd.bannert@anolilab.de',
                ],
            ],
            'autoload'    => $autoload,
            'require'     => $require,
            'extra'       => $extra,
        ];

        if (\count($devRequire) !== 0) {
            $composerJsonContent['require-dev'] = $devRequire;
        }

        return \json_encode($composerJsonContent, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param string $name
     * @param array  $require
     * @param array  $devRequire
     * @param array  $discoveryExtra
     *
     * @return string
     */
    public static function createDiscoveryComposerJson(string $name, array $require = [], array $devRequire = [], array $discoveryExtra = []): string
    {
        $extendedExtra = [
            'discovery' => $discoveryExtra,
        ];

        return self::createComposerJson($name, $require, $devRequire, [], $extendedExtra);
    }

    /**
     * @param string $name
     * @param array  $require
     * @param array  $autoload
     *
     * @return string
     */
    public static function createComposerPluginJson(string $name, array $require = [], array $autoload = []): string
    {
        $composerJsonContent = [
            'name'        => $name,
            'type'        => 'discovery-configurator',
            'description' => 'plugin',
            'authors'     => [
                [
                    'name'  => 'Daniel Bannert',
                    'email' => 'd.bannert@anolilab.de',
                ],
            ],
            'autoload'    => $autoload,
            'require'     => $require,
        ];

        return \json_encode($composerJsonContent, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param string $jsonFilePath
     *
     * @return array
     */
    public static function jsonToArray(string $jsonFilePath): array
    {
        return \json_decode(\file_get_contents($jsonFilePath), true);
    }
}
