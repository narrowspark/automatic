<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security;

use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\VersionParser;
use Narrowspark\Automatic\Security\Contract\Downloader as DownloaderContract;
use Narrowspark\Automatic\Security\Contract\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

class Audit
{
    /**
     * @var string
     */
    private const SECURITY_ADVISORIES_BASE_URL = 'https://raw.githubusercontent.com/narrowspark/security-advisories/master/';

    /**
     * @var string
     */
    private const SECURITY_ADVISORIES_SHA = 'security-advisories-sha';

    /**
     * @var string
     */
    private const SECURITY_ADVISORIES = 'security-advisories.json';

    /**
     * A Filesystem instance.
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * A version parser instance.
     *
     * @var \Composer\Semver\VersionParser
     */
    private $versionParser;

    /**
     * The composer vendor path.
     *
     * @var string
     */
    private $composerVendorPath;

    /**
     * A downloader instance.
     *
     * @var \Narrowspark\Automatic\Security\Contract\Downloader
     */
    private $downloader;

    /**
     * Create a new Audit instance.
     *
     * @param string                                              $composerVendorPath
     * @param \Narrowspark\Automatic\Security\Contract\Downloader $downloader
     */
    public function __construct(string $composerVendorPath, DownloaderContract $downloader)
    {
        $this->composerVendorPath = $composerVendorPath;
        $this->downloader         = $downloader;
        $this->versionParser      = new VersionParser();
        $this->filesystem         = new Filesystem();
    }

    /**
     * Checks a package on name and version.
     *
     * @param string $name
     * @param string $version
     * @param array  $securityAdvisories
     *
     * @return array[]
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
     * Checks a composer lock file.
     *
     * @param string $lock The path to the composer.lock file
     *
     * @throws \Narrowspark\Automatic\Security\Contract\Exception\RuntimeException When the lock file does not exist
     *
     * @return array[]
     */
    public function checkLock(string $lock): array
    {
        if (! \file_exists($lock)) {
            throw new RuntimeException('Lock file does not exist.');
        }

        $lockContents = $this->getLockContents($lock);

        /** @var \Composer\Package\Package[] $packages */
        $packages = [];

        foreach (['packages', 'packages-dev'] as $key) {
            $data = $lockContents[$key];

            foreach ($data as $pkgData) {
                $packages[] = new Package($pkgData['name'], $this->versionParser->normalize($pkgData['version']), $pkgData['version']);
            }
        }

        $securityAdvisories = $this->getSecurityAdvisories();
        $vulnerabilities    = [];
        $messages           = [];

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
     * Get the news security advisories from narrowspark/security-advisories.
     *
     * @param null|\Composer\IO\IOInterface $io
     *
     * @return array<string, array>
     */
    public function getSecurityAdvisories(?IOInterface $io = null): array
    {
        $sha = $this->downloader->download(self::SECURITY_ADVISORIES_BASE_URL . self::SECURITY_ADVISORIES_SHA);

        $narrowsparkAutomaticPath = $this->composerVendorPath . \DIRECTORY_SEPARATOR . 'narrowspark' . \DIRECTORY_SEPARATOR . 'automatic' . \DIRECTORY_SEPARATOR;

        if (! $this->filesystem->exists($narrowsparkAutomaticPath)) {
            $this->filesystem->mkdir($narrowsparkAutomaticPath);
        }

        $securityAdvisoriesShaPath = $narrowsparkAutomaticPath . self::SECURITY_ADVISORIES_SHA;
        $securityAdvisoriesPath    = $narrowsparkAutomaticPath . self::SECURITY_ADVISORIES;

        if ($this->filesystem->exists($securityAdvisoriesShaPath)) {
            $oldSha = \file_get_contents($securityAdvisoriesShaPath);

            if ($oldSha === $sha) {
                return \json_decode((string) \file_get_contents($securityAdvisoriesPath), true);
            }
        }

        if ($io !== null) {
            $io->writeError('Downloading the Security Advisories database...', true, IOInterface::VERBOSE);
        }

        $securityAdvisories = $this->downloader->download(self::SECURITY_ADVISORIES_BASE_URL . self::SECURITY_ADVISORIES);

        $this->filesystem->dumpFile($securityAdvisoriesShaPath, $sha);
        $this->filesystem->dumpFile($securityAdvisoriesPath, $securityAdvisories);

        return \json_decode((string) \file_get_contents($securityAdvisoriesPath), true);
    }

    /**
     * @param string $lock
     *
     * @return array
     */
    private function getLockContents(string $lock): array
    {
        $contents = \json_decode((string) \file_get_contents($lock), true);
        $packages = ['packages' => [], 'packages-dev' => []];

        foreach (['packages', 'packages-dev'] as $key) {
            if (! \is_array($contents[$key])) {
                continue;
            }

            foreach ($contents[$key] as $package) {
                $data = [
                    'name'    => $package['name'],
                    'version' => $package['version'],
                ];

                if (isset($package['time']) && false !== \mb_strpos($package['version'], 'dev')) {
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
     * @param array                     $securityAdvisories
     * @param \Composer\Package\Package $package
     * @param array                     $messages
     * @param array                     $vulnerabilities
     *
     * @return array[]
     */
    private function checkPackageAgainstSecurityAdvisories(
        array $securityAdvisories,
        Package $package,
        array $messages        = [],
        array $vulnerabilities = []
    ): array {
        $name = $package->getName();

        foreach ($securityAdvisories[$name] as $key => $advisoryData) {
            if (! \is_array($advisoryData['branches'])) {
                $messages[$name][] = '"branches" is expected to be an array.';

                continue;
            }

            foreach ($advisoryData['branches'] as $name => $branch) {
                if (! isset($branch['versions'])) {
                    $messages[$name][] = \sprintf('Key [versions] is not set for branch [%s].', $key);
                } elseif (! \is_array($branch['versions'])) {
                    $messages[$name][] = \sprintf('Key [versions] is expected to be an array for branch [%s].', $key);
                } else {
                    $constraints = [];

                    foreach ($branch['versions'] as $version) {
                        $op = null;

                        foreach (Constraint::getSupportedOperators() as $operators) {
                            if (\mb_strpos($version, $operators) === 0) {
                                $op = $operators;

                                break;
                            }
                        }

                        if (null === $op) {
                            $messages[$name][] = \sprintf('Version [%s] does not contain a supported operator.', $version);

                            continue;
                        }

                        $constraints[] = new Constraint($op, \mb_substr($version, \mb_strlen($op)));
                    }

                    $affectedConstraint = new MultiConstraint($constraints);
                    $affectedPackage    = $affectedConstraint->matches(new Constraint('==', $package->getVersion()));

                    if ($affectedPackage) {
                        $composerPackage = \mb_substr($advisoryData['reference'], 11);

                        $vulnerabilities[$composerPackage] = $vulnerabilities[$composerPackage] ?? [
                            'version'    => $package->getPrettyVersion(),
                            'advisories' => [],
                        ];

                        $vulnerabilities[$composerPackage]['advisories'][$key] = [
                            'title' => $advisoryData['title'] ?? '',
                            'link'  => $advisoryData['link'] ?? '',
                            'cve'   => $advisoryData['cve'] ?? '',
                        ];
                    }
                }
            }
        }

        return [$messages, $vulnerabilities];
    }
}
