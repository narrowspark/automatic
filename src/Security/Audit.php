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

namespace Narrowspark\Automatic\Security;

use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\VersionParser;
use Narrowspark\Automatic\Common\Downloader\Downloader;
use Narrowspark\Automatic\Security\Contract\Audit as AuditContract;
use Narrowspark\Automatic\Security\Contract\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

final class Audit implements AuditContract
{
    /**
     * A Filesystem instance.
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    private $filesystem;

    /**
     * A version parser instance.
     *
     * @var \Composer\Semver\VersionParser
     */
    private $versionParser;

    /**
     * A downloader instance.
     *
     * @var \Narrowspark\Automatic\Common\Downloader\Downloader
     */
    private $downloader;

    /**
     * Check if composer is in dev mode.
     *
     * @var bool
     */
    private $devMode = true;

    /**
     * Create a new Audit instance.
     */
    public function __construct(Downloader $downloader)
    {
        $this->downloader = $downloader;
        $this->versionParser = new VersionParser();
        $this->filesystem = new Filesystem();
    }

    /**
     * {@inheritdoc}
     */
    public function setDevMode(bool $bool): void
    {
        $this->devMode = $bool;
    }

    public static function getUserAgent(): string
    {
        return \sprintf(
            'Narrowspark-Security-Audit/%s (%s; %s; %s%s)',
            Plugin::VERSION,
            \function_exists('php_uname') ? \php_uname('s') : 'Unknown',
            \function_exists('php_uname') ? \php_uname('r') : 'Unknown',
            'PHP ' . \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION . '.' . \PHP_RELEASE_VERSION,
            \getenv('CI') !== false ? '; CI' : ''
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed[][]
     */
    public function checkPackage(string $name, string $version, array $securityAdvisories): array
    {
        if (! isset($securityAdvisories[$name])) {
            return [];
        }

        $package = new Package($name, $this->versionParser->normalize($version), $version);

        [$messages, $vulnerabilities] = $this->checkPackageAgainstSecurityAdvisories($securityAdvisories, $package);

        \ksort($vulnerabilities);

        return [$vulnerabilities, $messages];
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed[][]
     */
    public function checkLock(string $lock): array
    {
        if (! $this->filesystem->exists($lock)) {
            throw new RuntimeException('Lock file does not exist.');
        }

        $lockContents = $this->getLockContents($lock);

        /** @var \Composer\Package\Package[] $packages */
        $packages = [];
        $keys = ['packages'];

        if ($this->devMode) {
            $keys[] = 'packages-dev';
        }

        foreach ($keys as $key) {
            $data = $lockContents[$key];

            foreach ($data as $pkgData) {
                $packages[] = new Package($pkgData['name'], $this->versionParser->normalize($pkgData['version']), $pkgData['version']);
            }
        }

        $securityAdvisories = $this->getSecurityAdvisories();
        $vulnerabilities = [];
        $messages = [];

        foreach ($packages as $package) {
            if (! isset($securityAdvisories[$package->getName()])) {
                continue;
            }

            [$messages, $vulnerabilities] = $this->checkPackageAgainstSecurityAdvisories($securityAdvisories, $package, $messages, $vulnerabilities);
        }

        \ksort($vulnerabilities);

        return [$vulnerabilities, $messages];
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed[]
     */
    public function getSecurityAdvisories(?IOInterface $io = null): array
    {
        if ($io !== null) {
            $io->writeError('Downloading the Security Advisories database...', true, IOInterface::VERBOSE);
        }

        $response = $this->downloader->get('/security-advisories.json', ['User-Agent:' . self::getUserAgent()]);

        return $response->getBody() ?? [];
    }

    /**
     * @return mixed[][][]|mixed[][][][]
     */
    private function getLockContents(string $lock): array
    {
        $contents = \json_decode((string) \file_get_contents($lock), true, 512, \JSON_THROW_ON_ERROR);
        $packages = ['packages' => [], 'packages-dev' => []];

        foreach (['packages', 'packages-dev'] as $key) {
            if (! \is_array($contents[$key])) {
                continue;
            }

            foreach ($contents[$key] as $package) {
                $data = [
                    'name' => $package['name'],
                    'version' => $package['version'],
                ];

                if (isset($package['time']) && false !== \strpos($package['version'], 'dev')) {
                    $data['time'] = $package['time'];
                }

                $packages[$key][] = $data;
            }
        }

        return $packages;
    }

    /**
     * Check if a package has some security issues.
     *
     * @return array[]
     */
    private function checkPackageAgainstSecurityAdvisories(
        array $securityAdvisories,
        Package $package,
        array $messages = [],
        array $vulnerabilities = []
    ): array {
        $name = $package->getName();

        foreach ($securityAdvisories[$name] as $key => $advisoryData) {
            if (! \is_array($advisoryData['branches'])) {
                $messages[$name][] = '"branches" is expected to be an array.';

                continue;
            }

            foreach ($advisoryData['branches'] as $n => $branch) {
                if (! isset($branch['versions'])) {
                    $messages[$n][] = \sprintf('Key [versions] is not set for branch [%s].', $key);
                } elseif (! \is_array($branch['versions'])) {
                    $messages[$n][] = \sprintf('Key [versions] is expected to be an array for branch [%s].', $key);
                } else {
                    $constraints = [];

                    foreach ($branch['versions'] as $version) {
                        $op = null;

                        foreach (Constraint::getSupportedOperators() as $operators) {
                            if (\strpos($version, (string) $operators) === 0) {
                                $op = $operators;

                                break;
                            }
                        }

                        if (null === $op) {
                            $messages[$n][] = \sprintf('Version [%s] does not contain a supported operator.', $version);

                            continue;
                        }

                        $constraints[] = new Constraint($op, \substr($version, \strlen($op)));
                    }

                    $affectedConstraint = new MultiConstraint($constraints);
                    $affectedPackage = $affectedConstraint->matches(new Constraint('==', $package->getVersion()));

                    if ($affectedPackage) {
                        $composerPackage = \substr($advisoryData['reference'], 11);

                        $vulnerabilities[$composerPackage] = $vulnerabilities[$composerPackage] ?? [
                            'version' => $package->getPrettyVersion(),
                            'advisories' => [],
                        ];

                        $vulnerabilities[$composerPackage]['advisories'][$key] = [
                            'title' => $advisoryData['title'] ?? '',
                            'link' => $advisoryData['link'] ?? '',
                            'cve' => $advisoryData['cve'] ?? '',
                        ];
                    }
                }
            }
        }

        return [$messages, $vulnerabilities];
    }
}
