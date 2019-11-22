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

namespace Narrowspark\Automatic\Security\Test;

use Narrowspark\Automatic\Security\Audit;
use Narrowspark\Automatic\Security\Contract\Exception\RuntimeException;
use Narrowspark\Automatic\Security\Downloader\ComposerDownloader;
use Narrowspark\TestingHelper\Traits\AssertArrayTrait;
use PHPUnit\Framework\TestCase;
use const DIRECTORY_SEPARATOR;
use function array_map;
use function array_merge;
use function count;
use function glob;
use function is_dir;
use function rmdir;
use function unlink;

/**
 * @internal
 *
 * @small
 */
final class AuditTest extends TestCase
{
    use AssertArrayTrait;

    /** @var \Narrowspark\Automatic\Security\Audit */
    private $audit;

    /** @var string */
    private $path;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->path = __DIR__ . 'audit';

        $downloader = new ComposerDownloader();

        $this->audit = new Audit($this->path, $downloader, $downloader->download(Audit::SECURITY_ADVISORIES_BASE_URL . Audit::SECURITY_ADVISORIES_SHA));
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->delete($this->path);
        @rmdir($this->path);
    }

    public function testCheckPackageWithSymfony(): void
    {
        [$vulnerabilities, $messages] = $this->audit->checkPackage('symfony/symfony', 'v2.5.2', $this->audit->getSecurityAdvisories());

        $this->assertSymfonySecurity(count($vulnerabilities), $vulnerabilities);
        self::assertCount(0, $messages);
    }

    public function testCheckPackageWithSymfonyAndCache(): void
    {
        [$vulnerabilities, $messages] = $this->audit->checkPackage('symfony/symfony', 'v2.5.2', $this->audit->getSecurityAdvisories());

        $this->assertSymfonySecurity(count($vulnerabilities), $vulnerabilities);
        self::assertCount(0, $messages);

        [$vulnerabilities, $messages] = $this->audit->checkPackage('symfony/symfony', 'v2.5.2', $this->audit->getSecurityAdvisories());

        $this->assertSymfonySecurity(count($vulnerabilities), $vulnerabilities);
    }

    public function testCheckLockWithSymfony252(): void
    {
        [$vulnerabilities, $messages] = $this->audit->checkLock(
            __DIR__ . DIRECTORY_SEPARATOR . 'Fixture' . DIRECTORY_SEPARATOR . 'symfony_2.5.2_composer.lock'
        );

        $this->assertSymfonySecurity(count($vulnerabilities), $vulnerabilities);
        self::assertCount(0, $messages);
    }

    public function testCheckLockWithSymfony252OnDevPackageWithNoDevMode(): void
    {
        $this->audit->setDevMode(false);

        [$vulnerabilities, $messages] = $this->audit->checkLock(
            __DIR__ . DIRECTORY_SEPARATOR . 'Fixture' . DIRECTORY_SEPARATOR . 'symfony_2.5.2_dev_packages_composer.lock'
        );

        self::assertCount(0, $vulnerabilities);
        self::assertCount(0, $messages);
    }

    public function testCheckLockWithComposer171(): void
    {
        [$vulnerabilities, $messages] = $this->audit->checkLock(
            __DIR__ . DIRECTORY_SEPARATOR . 'Fixture' . DIRECTORY_SEPARATOR . 'composer_1.7.1_composer.lock'
        );

        self::assertCount(0, $vulnerabilities);
        self::assertCount(0, $messages);
    }

    public function testCheckLockThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lock file does not exist.');

        $this->audit->checkLock('');
    }

    public function testCheckPackageWithCustomPackage(): void
    {
        self::assertCount(0, $this->audit->checkPackage('fooa/fooa', 'v2.5.2', $this->audit->getSecurityAdvisories()));
    }

    /**
     * @param int   $vulnerabilitiesCount
     * @param array $vulnerabilities
     *
     * @return void
     */
    private function assertSymfonySecurity(int $vulnerabilitiesCount, array $vulnerabilities): void
    {
        self::assertArraySubset(
            array_merge([
                'symfony/symfony' => [
                    'version' => 'v2.5.2',
                    'advisories' => [
                        'CVE-2016-4423' => [
                            'title' => 'CVE-2016-4423: Large username storage in session',
                            'link' => 'https://symfony.com/cve-2016-4423',
                            'cve' => 'CVE-2016-4423',
                        ],
                        'CVE-2017-16654' => [
                            'title' => 'CVE-2017-16654: Intl bundle readers breaking out of paths',
                            'link' => 'https://symfony.com/cve-2017-16654',
                            'cve' => 'CVE-2017-16654',
                        ],
                        'CVE-2017-16652' => [
                            'title' => 'CVE-2017-16652: Open redirect vulnerability on security handlers',
                            'link' => 'https://symfony.com/cve-2017-16652',
                            'cve' => 'CVE-2017-16652',
                        ],
                        'CVE-2014-6061' => [
                            'title' => 'Security issue when parsing the Authorization header',
                            'link' => 'https://symfony.com/cve-2014-6061',
                            'cve' => 'CVE-2014-6061',
                        ],
                        'CVE-2015-4050' => [
                            'title' => 'CVE-2015-4050: ESI unauthorized access',
                            'link' => 'https://symfony.com/cve-2015-4050',
                            'cve' => 'CVE-2015-4050',
                        ],
                        'CVE-2018-11408' => [
                            'title' => 'CVE-2018-11408: Open redirect vulnerability on security handlers',
                            'link' => 'https://symfony.com/cve-2018-11408',
                            'cve' => 'CVE-2018-11408',
                        ],
                        'CVE-2018-11385' => [
                            'title' => 'CVE-2018-11385: Session Fixation Issue for Guard Authentication',
                            'link' => 'https://symfony.com/cve-2018-11385',
                            'cve' => 'CVE-2018-11385',
                        ],
                        'CVE-2014-4931' => [
                            'title' => 'Code injection in the way Symfony implements translation caching in FrameworkBundle',
                            'link' => 'https://symfony.com/blog/security-releases-cve-2014-4931-symfony-2-3-18-2-4-8-and-2-5-2-released',
                            'cve' => 'CVE-2014-4931',
                        ],
                        'CVE-2016-1902' => [
                            'title' => 'CVE-2016-1902: SecureRandom\'s fallback not secure when OpenSSL fails ',
                            'link' => 'https://symfony.com/cve-2016-1902',
                            'cve' => 'CVE-2016-1902',
                        ],
                        'CVE-2018-14773' => [
                            'title' => 'CVE-2018-14773: Remove support for legacy and risky HTTP headers',
                            'link' => 'https://symfony.com/blog/cve-2018-14773-remove-support-for-legacy-and-risky-http-headers',
                            'cve' => 'CVE-2018-14773',
                        ],
                        'CVE-2015-8124' => [
                            'title' => 'CVE-2015-8124: Session Fixation in the "Remember Me" Login Feature',
                            'link' => 'https://symfony.com/cve-2015-8124',
                            'cve' => 'CVE-2015-8124',
                        ],
                        'CVE-2015-2309' => [
                            'title' => 'Unsafe methods in the Request class',
                            'link' => 'https://symfony.com/cve-2015-2309',
                            'cve' => 'CVE-2015-2309',
                        ],
                        'CVE-2017-16653' => [
                            'title' => 'CVE-2017-16653: CSRF protection does not use different tokens for HTTP and HTTPS',
                            'link' => 'https://symfony.com/cve-2017-16653',
                            'cve' => 'CVE-2017-16653',
                        ],
                        'CVE-2017-11365' => [
                            'title' => 'CVE-2017-11365: Empty passwords validation issue',
                            'link' => 'https://symfony.com/cve-2017-11365',
                            'cve' => 'CVE-2017-11365',
                        ],
                        'CVE-2018-11386' => [
                            'title' => 'CVE-2018-11386: Denial of service when using PDOSessionHandler',
                            'link' => 'https://symfony.com/cve-2018-11386',
                            'cve' => 'CVE-2018-11386',
                        ],
                        'CVE-2018-11406' => [
                            'title' => 'CVE-2018-11406: CSRF Token Fixation',
                            'link' => 'https://symfony.com/cve-2018-11406',
                            'cve' => 'CVE-2018-11406',
                        ],
                        'CVE-2014-6072' => [
                            'title' => 'CSRF vulnerability in the Web Profiler',
                            'link' => 'https://symfony.com/cve-2014-6072',
                            'cve' => 'CVE-2014-6072',
                        ],
                        'CVE-2018-11407' => [
                            'title' => 'CVE-2018-11407: Unauthorized access on a misconfigured LDAP server when using an empty password',
                            'link' => 'https://symfony.com/cve-2018-11407',
                            'cve' => 'CVE-2018-11407',
                        ],
                        'CVE-2015-8125' => [
                            'title' => 'CVE-2015-8125: Potential Remote Timing Attack Vulnerability in Security Remember-Me Service',
                            'link' => 'https://symfony.com/cve-2015-8125',
                            'cve' => 'CVE-2015-8125',
                        ],
                        'CVE-2015-2308' => [
                            'title' => 'Esi Code Injection',
                            'link' => 'https://symfony.com/cve-2015-2308',
                            'cve' => 'CVE-2015-2308',
                        ],
                        'CVE-2016-2403' => [
                            'title' => 'CVE-2016-2403: Unauthorized access on a misconfigured Ldap server when using an empty password',
                            'link' => 'https://symfony.com/cve-2016-2403',
                            'cve' => 'CVE-2016-2403',
                        ],
                        'CVE-2014-5244' => [
                            'title' => 'Denial of service with a malicious HTTP Host header',
                            'link' => 'https://symfony.com/cve-2014-5244',
                            'cve' => 'CVE-2014-5244',
                        ],
                        'CVE-2014-5245' => [
                            'title' => 'Direct access of ESI URLs behind a trusted proxy',
                            'link' => 'https://symfony.com/cve-2014-5245',
                            'cve' => 'CVE-2014-5245',
                        ],
                        'CVE-2017-16790' => [
                            'title' => 'CVE-2017-16790: Ensure that submitted data are uploaded files',
                            'link' => 'https://symfony.com/cve-2017-16790',
                            'cve' => 'CVE-2017-16790',
                        ],
                        'CVE-2018-19789' => [
                            'title' => 'CVE-2018-19789: Temporary uploaded file path disclosure',
                            'link' => 'https://symfony.com/cve-2018-19789',
                            'cve' => 'CVE-2018-19789',
                        ],
                        'CVE-2018-19790' => [
                            'title' => 'CVE-2018-19790: Open Redirect Vulnerability on login',
                            'link' => 'https://symfony.com/cve-2018-19790',
                            'cve' => 'CVE-2018-19790',
                        ],
                        'CVE-2019-10909' => [
                            'title' => 'CVE-2019-10909: Escape validation messages in the PHP templating engine',
                            'link' => 'https://symfony.com/cve-2019-10909',
                            'cve' => 'CVE-2019-10909',
                        ],
                        'CVE-2019-10910' => [
                            'title' => 'CVE-2019-10910: Check service IDs are valid',
                            'link' => 'https://symfony.com/cve-2019-10910',
                            'cve' => 'CVE-2019-10910',
                        ],
                        'CVE-2019-10911' => [
                            'title' => 'CVE-2019-10911: Add a separator in the remember me cookie hash',
                            'link' => 'https://symfony.com/cve-2019-10911',
                            'cve' => 'CVE-2019-10911',
                        ],
                        'CVE-2019-10912' => [
                            'title' => 'CVE-2019-10912: Prevent destructors with side-effects from being unserialized',
                            'link' => 'https://symfony.com/cve-2019-10912',
                            'cve' => 'CVE-2019-10912',
                        ],
                        'CVE-2019-10913' => [
                            'title' => 'CVE-2019-10913: Reject invalid HTTP method overrides',
                            'link' => 'https://symfony.com/cve-2019-10913',
                            'cve' => 'CVE-2019-10913',
                        ],
                    ],
                ],
            ], $vulnerabilitiesCount === 2 ? ['twig/twig' => [
                'version' => 'v1.35.4',
                'advisories' => [
                    '2019-03-12' => [
                        'title' => 'Sandbox Information Disclosure',
                        'link' => 'https://symfony.com/blog/twig-sandbox-information-disclosure',
                        'cve' => null,
                    ],
                ],
            ]] : []),
            $vulnerabilities
        );
    }

    private function delete(string $path): void
    {
        array_map(function ($value): void {
            if (is_dir($value)) {
                $this->delete($value);

                @rmdir($value);
            } else {
                @unlink($value);
            }
        }, glob($path . DIRECTORY_SEPARATOR . '*'));
    }
}
