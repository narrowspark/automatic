<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Prefetcher;

use Composer\Cache as BaseComposerCache;

/**
 * Ported from symfony flex, see original.
 *
 * @see https://github.com/symfony/flex/blob/master/src/Cache.php
 *
 * (c) Nicolas Grekas <p@tchwork.com>
 */
class Cache extends BaseComposerCache
{
    /**
     * @var array
     */
    private static $lowestTags = [
        'symfony/symfony' => [
            'version'  => 'v3.4.0',
            'replaces' => [
                'symfony/asset',
                'symfony/browser-kit',
                'symfony/cache',
                'symfony/config',
                'symfony/console',
                'symfony/css-selector',
                'symfony/dependency-injection',
                'symfony/debug',
                'symfony/debug-bundle',
                'symfony/doctrine-bridge',
                'symfony/dom-crawler',
                'symfony/dotenv',
                'symfony/event-dispatcher',
                'symfony/expression-language',
                'symfony/filesystem',
                'symfony/finder',
                'symfony/form',
                'symfony/framework-bundle',
                'symfony/http-foundation',
                'symfony/http-kernel',
                'symfony/inflector',
                'symfony/intl',
                'symfony/ldap',
                'symfony/lock',
                'symfony/messenger',
                'symfony/monolog-bridge',
                'symfony/options-resolver',
                'symfony/process',
                'symfony/property-access',
                'symfony/property-info',
                'symfony/proxy-manager-bridge',
                'symfony/routing',
                'symfony/security',
                'symfony/security-core',
                'symfony/security-csrf',
                'symfony/security-guard',
                'symfony/security-http',
                'symfony/security-bundle',
                'symfony/serializer',
                'symfony/stopwatch',
                'symfony/templating',
                'symfony/translation',
                'symfony/twig-bridge',
                'symfony/twig-bundle',
                'symfony/validator',
                'symfony/var-dumper',
                'symfony/web-link',
                'symfony/web-profiler-bundle',
                'symfony/web-server-bundle',
                'symfony/workflow',
                'symfony/yaml',
            ],
        ],
    ];

    /**
     * @param string $file
     *
     * @return bool|string
     */
    public function read($file)
    {
        $content = parent::read($file);

        if (\mb_strpos($file, 'provider-symfony$') === 0 && \is_array($data = \json_decode($content, true))) {
            $content = \json_encode($this->removeLegacyTags($data));
        }

        return $content;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function removeLegacyTags(array $data): array
    {
        if (($symfonyVersion = \getenv('SYMFONY_LOWEST_VERSION')) !== false) {
            self::$lowestTags['symfony/symfony']['version'] = $symfonyVersion;
        }

        foreach (self::$lowestTags as $lowestPackage => $settings) {
            $lowestVersion    = $settings['version'];
            $replacedPackages = $settings['replaces'];

            if (! isset($data['packages'][$lowestPackage][$lowestVersion])) {
                continue;
            }

            foreach ($data['packages'] as $package => $versions) {
                if ($package !== $lowestPackage && ! \in_array($package, $replacedPackages, true)) {
                    continue;
                }

                foreach ($versions as $version => $composerJson) {
                    if (\version_compare($version, $lowestVersion, '<')) {
                        unset($data['packages'][$package][$version]);
                    }
                }
            }

            break;
        }

        return $data;
    }
}
