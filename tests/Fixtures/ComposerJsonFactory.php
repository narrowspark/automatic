<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Fixtures;

class ComposerJsonFactory
{
    /**
     * @param string $name
     * @param array  $require
     * @param array  $devRequire
     * @param array  $extra
     *
     * @return string
     */
    public static function createSimpleComposerJson(string $name, array $require = [], array $devRequire = [], array $extra = []): string
    {
        $composerJsonContent = [
            'name'    => $name,
            'authors' => [
                [
                    'name'  => 'Daniel Bannert',
                    'email' => 'd.bannert@anolilab.de',
                ],
            ],
            'require'     => $require,
            'dev-require' => $devRequire,
            'extra'       => $extra,
        ];

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

        return self::createSimpleComposerJson($name, $require, $devRequire, $extendedExtra);
    }
}
