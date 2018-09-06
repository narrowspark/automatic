<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security;

use Composer\Package\Package;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\VersionParser;
use Symfony\Component\Filesystem\Filesystem;

class Audit
{
    private const SECURITY_ADVISORIES_BASE_URL = 'https://raw.githubusercontent.com/narrowspark/security-advisories/master/';

    private const SECURITY_ADVISORIES_SHA = 'security-advisories-sha';
    private const SECURITY_ADVISORIES     = 'security-advisories.json';

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
     * @var \Narrowspark\Automatic\Security\Downloader
     */
    private $downloader;

    /**
     * Create a new Audit instance.
     *
     * @param string $composerVendorPath
     */
    public function __construct(string $composerVendorPath)
    {
        $this->composerVendorPath = $composerVendorPath;
        $this->versionParser      = new VersionParser();
        $this->downloader         = new Downloader();
        $this->filesystem         = new Filesystem();
    }

    public function checkPackage(string $name, string $version): array
    {
        $package = new Package($name, $this->versionParser->normalize($version), $version);
    }

    public function checkLock(string $lock): array
    {
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
            $name = $package->getName();

            if (! isset($securityAdvisories[$name])) {
                continue;
            }

            foreach ($securityAdvisories[$name] as $key => $advisoryData) {
                foreach ($advisoryData['branches'] as $name => $branch) {
                    if (! isset($branch['versions'])) {
                        $messages[$name][] = \sprintf('Key "versions" is not set for branch "%s".', $key);
                    } elseif (! \is_array($branch['versions'])) {
                        $messages[$name][] = \sprintf('"versions" is expected to be an array for branch "%s".', $key);
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
                                $messages[$name][] = \sprintf('Version "%s" does not contain a supported operator.', $version);

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
        }

        if (\count($messages) !== 0) {
            \var_dump($messages);
        }

        \ksort($vulnerabilities);

        return [\count($vulnerabilities), $vulnerabilities];
    }

    private function getSecurityAdvisories(): array
    {
        if (! \extension_loaded('curl')) {
            $sha = $this->downloader->downloadWithComposer(self::SECURITY_ADVISORIES_BASE_URL . self::SECURITY_ADVISORIES_SHA);
        } else {
            $sha = $this->downloader->downloadWithCurl(self::SECURITY_ADVISORIES_BASE_URL . self::SECURITY_ADVISORIES_SHA);
        }

        $narrowsparkAutomaticPath = $this->composerVendorPath . \DIRECTORY_SEPARATOR . 'narrowspark' . \DIRECTORY_SEPARATOR . 'automatic' . \DIRECTORY_SEPARATOR;

        if (! $this->filesystem->exists($narrowsparkAutomaticPath)) {
            $this->filesystem->mkdir($narrowsparkAutomaticPath);
        }

        $securityAdvisoriesShaPath = $narrowsparkAutomaticPath . self::SECURITY_ADVISORIES_SHA;
        $securityAdvisoriesPath    = $narrowsparkAutomaticPath . self::SECURITY_ADVISORIES;

        if ($this->filesystem->exists($securityAdvisoriesShaPath)) {
            $oldSha = \file_get_contents($securityAdvisoriesShaPath);

            if ($oldSha === $sha) {
                return \json_decode(\file_get_contents($securityAdvisoriesPath), true);
            }
        }

        if (! \extension_loaded('curl')) {
            $securityAdvisories = $this->downloader->downloadWithComposer(self::SECURITY_ADVISORIES_BASE_URL . self::SECURITY_ADVISORIES);
        } else {
            $securityAdvisories = $this->downloader->downloadWithCurl(self::SECURITY_ADVISORIES_BASE_URL . self::SECURITY_ADVISORIES);
        }

        $this->filesystem->dumpFile($securityAdvisoriesShaPath, $sha);
        $this->filesystem->dumpFile($securityAdvisoriesPath, $securityAdvisories);

        return \json_decode(\file_get_contents($securityAdvisoriesPath), true);
    }

    /**
     * @param string $lock
     *
     * @return array
     */
    private function getLockContents(string $lock): array
    {
        $contents = \json_decode(\file_get_contents($lock), true);
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
}
