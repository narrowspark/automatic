<?php
declare(strict_types=1);
namespace Narrowspark\Automatic;

use Composer\IO\IOInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;

final class LegacyTagsManager
{
    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * A version parser instance.
     *
     * @var \Composer\Semver\VersionParser
     */
    private $versionParser;

    /**
     * A list of the legacy tags versions and constraints.
     *
     * @var array<string, array<string, \Composer\Semver\Constraint\ConstraintInterface|string>>
     */
    private $legacyTags = [];

    /**
     * LegacyTagsManager constructor.
     *
     * @param IOInterface $io
     */
    public function __construct(IOInterface $io)
    {
        $this->io            = $io;
        $this->versionParser = new VersionParser();
    }

    /**
     * Add a legacy package constraint.
     *
     * @param string $name
     * @param string $require
     *
     * @return void
     */
    public function addConstraint(string $name, string $require): void
    {
        $this->legacyTags[$name] = [
            'version'   => $require,
            'constrain' => $this->versionParser->parseConstraints($require),
        ];
    }

    /**
     * Check if the provider is supported.
     *
     * @param string $file the composer provider file name
     *
     * @return bool
     */
    public function hasProvider(string $file): bool
    {
        foreach ($this->legacyTags as $name => $constraint) {
            [$namespace, $packageName] = \explode('/', $name, 2);

            if (\mb_strpos($file, \sprintf('provider-%s$', $namespace)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove legacy tags from packages.
     *
     * @param array $data
     *
     * @return array
     */
    public function removeLegacyTags(array $data): array
    {
        if (! isset($data['packages'])) {
            return $data;
        }

        $regex = '/^(\d++\.\d++)\..*/';

        foreach ($data['packages'] as $name => $versions) {
            if (! isset($this->legacyTags[$name])) {
                continue;
            }

            foreach ($versions as $version => $package) {
                foreach ($this->legacyTags[$name] as $legacyName => $legacyVersion) {
                    if (isset($data['packages'][$legacyName]) &&
                        ($data['packages'][$legacyName][\preg_replace($regex, '$1.x-dev', $version)]['replace'][$name] ?? null) !== 'self.version'
                    ) {
                        continue;
                    }
                }

                $normalizedVersion = $package['extra']['branch-alias'][$version] ?? null;
                $normalizedVersion = $normalizedVersion ? $this->versionParser->normalize($normalizedVersion) : $package['version_normalized'];

                /** @var \Composer\Semver\Constraint\Constraint $constrain */
                $constrain = $this->legacyTags[$name]['constrain'];

                if (! $constrain->matches(new Constraint('==', $normalizedVersion))) {
                    $this->io->writeError(\sprintf('<info>Restricting packages listed in [%s] to [%s]</info>', $name, (string) $this->legacyTags[$name]['version']));

                    unset($data['packages'][$name][$version]);
                }
            }
        }

        return $data;
    }
}
