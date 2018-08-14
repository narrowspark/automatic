<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Prefetcher;

use Composer\Cache as BaseComposerCache;
use Composer\IO\IOInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;
use Composer\Util\Filesystem;

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
     * A version parser instance.
     *
     * @var \Composer\Semver\VersionParser
     */
    private $versionParser;

    /**
     * A composer constraint implementation.
     *
     * @var \Composer\Semver\Constraint\ConstraintInterface
     */
    private $symfonyRequire;

    /**
     * {@inheritdoc}
     */
    public function __construct(IOInterface $io, $cacheDir, $whitelist = 'a-z0-9.', Filesystem $filesystem = null)
    {
        parent::__construct($io, $cacheDir, $whitelist, $filesystem);

        $this->versionParser = new VersionParser();
    }

    /**
     * @param string $symfonyRequire
     */
    public function setSymfonyRequire(string $symfonyRequire): void
    {
        $this->symfonyRequire = $this->versionParser->parseConstraints($symfonyRequire);
    }

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
     * Helper to remove legacy symfony tags.
     *
     * @param array $data
     *
     * @return array
     */
    public function removeLegacyTags(array $data): array
    {
        if ($this->symfonyRequire === null || ! isset($data['packages']['symfony/symfony'])) {
            return $data;
        }

        $symfonyVersions = $data['packages']['symfony/symfony'];

        foreach ($data['packages'] as $name => $versions) {
            foreach ($versions as $version => $package) {
                if ('symfony/symfony' !== $name && ! isset($symfonyVersions[\preg_replace('/^(\d++\.\d++)\..*/', '$1.x-dev', $version)]['replace'][$name])) {
                    continue;
                }

                $normalizedVersion = $package['extra']['branch-alias'][$version] ?? null;
                $normalizedVersion = $normalizedVersion ? $this->versionParser->normalize($normalizedVersion) : $package['version_normalized'];

                $provider = new Constraint('==', $normalizedVersion);

                if (! $this->symfonyRequire->matches($provider)) {
                    unset($data['packages'][$name][$version]);
                }
            }
        }

        return $data;
    }
}
