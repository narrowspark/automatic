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

namespace Narrowspark\Automatic\Prefetcher;

use Composer\IO\IOInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;
use Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager as LegacyTagsManagerContract;

final class LegacyTagsManager implements LegacyTagsManagerContract
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

    /** @var array */
    private static $packageCache = [];

    /**
     * LegacyTagsManager constructor.
     *
     * @param IOInterface $io
     */
    public function __construct(IOInterface $io)
    {
        $this->io = $io;
        $this->versionParser = new VersionParser();
    }

    /**
     * {@inheritdoc}
     */
    public function addConstraint(string $name, string $require): void
    {
        $this->legacyTags[$name] = [
            'version' => $require,
            'constrain' => $this->versionParser->parseConstraints($require),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function hasProvider(string $file): bool
    {
        foreach ($this->legacyTags as $name => $constraint) {
            [$namespace,] = \explode('/', $name, 2);

            if (\strpos($file, \sprintf('provider-%s$', $namespace)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function removeLegacyTags(array $data): array
    {
        if (! isset($data['packages'])) {
            return $data;
        }

        $packagesReplace = [];
        $packages = [];

        foreach ($this->legacyTags as $name => $legacy) {
            if (! isset($data['packages'][$name])) {
                continue;
            }

            $packages[$name] = $data['packages'][$name];

            foreach ($data['packages'][$name] as $version => $composerJson) {
                if ($version === 'dev-master' && null !== $devMasterVersion = $composerJson['extra']['branch-alias']['dev-master'] ?? null) {
                    $normalizedVersion = $this->versionParser->normalize($devMasterVersion);
                } elseif (! isset($composerJson['version_normalized'])) {
                    continue;
                } else {
                    $normalizedVersion = $composerJson['version_normalized'];
                }

                /** @var \Composer\Semver\Constraint\Constraint $constrain */
                $constrain = $legacy['constrain'];

                if ($constrain->matches(new Constraint('==', $normalizedVersion))) {
                    if (isset($composerJson['replace'])) {
                        foreach ($composerJson['replace'] as $key => $value) {
                            $packagesReplace[$key] = $name;
                        }
                    }
                } else {
                    if (! isset(self::$packageCache[$name])) {
                        $this->io->writeError(
                            \sprintf('<info>Restricting packages listed in [%s] to [%s]</info>', $name, (string) $legacy['version'])
                        );
                        self::$packageCache[$name] = true;
                    }

                    unset($packages[$name][$version]);
                }
            }
        }

        if (\count(\array_filter($packages)) === 0) {
            return $data;
        }

        foreach ($packages as $key => $value) {
            $data['packages'][$key] = $value;
        }

        foreach ($data['packages'] as $name => $versions) {
            if (! isset($packagesReplace[$name])) {
                continue;
            }

            $parentName = $packagesReplace[$name];
            $devMaster = null;

            if (isset($versions['dev-master'])) {
                $devMaster = $versions['dev-master'];
            }

            $versions = \array_intersect_key($versions, $data['packages'][$parentName]);

            if ($devMaster !== null && null !== $devMasterAlias = $versions['dev-master']['extra']['branch-alias']['dev-master'] ?? null) {
                /** @var \Composer\Semver\Constraint\Constraint $legacyConstrain */
                $legacyConstrain = $this->legacyTags[$parentName]['constrain'];

                if ($legacyConstrain->matches(new Constraint('==', $this->versionParser->normalize($devMasterAlias)))) {
                    $versions['dev-master'] = $devMaster;
                }
            }

            if (\count($versions) !== 0) {
                $data['packages'][$name] = $versions;
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->legacyTags = [];
    }
}
