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

namespace Narrowspark\Automatic\Tests\Prefetcher;

use Composer\IO\IOInterface;
use Generator;
use Mockery;
use Narrowspark\Automatic\Common\Downloader\Downloader;
use Narrowspark\Automatic\Common\Downloader\JsonResponse;
use Narrowspark\Automatic\Prefetcher\LegacyTagsManager;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Prefetcher\LegacyTagsManager
 *
 * @medium
 */
final class LegacyTagsManagerTest extends MockeryTestCase
{
    /** @var array<string, string> */
    private $downloadFileList;

    /** @var \Composer\IO\IOInterface|\Mockery\MockInterface */
    private $ioMock;

    /** @var \Narrowspark\Automatic\Prefetcher\LegacyTagsManager */
    private $tagsManger;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $pPath = __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR . 'Packagist' . \DIRECTORY_SEPARATOR;

        $this->downloadFileList = [
            'cakephp$cakephp' => $pPath . 'provider-cakephp$cakephp.json',
            'codeigniter$framework' => $pPath . 'provider-codeigniter$framework.json',
            'symfony$security-guard' => $pPath . 'provider-symfony$security-guard.json',
            'symfony$symfony' => $pPath . 'provider-symfony$symfony.json',
            'zendframework$zend-diactoros' => $pPath . 'provider-zendframework$zend-diactoros.json',
        ];

        $responseMock = Mockery::mock(JsonResponse::class);
        $responseMock->shouldReceive('getBody')
            ->andReturn(self::getVersions());

        $downloaderMock = Mockery::mock(Downloader::class);
        $downloaderMock->shouldReceive('get')
            ->andReturn($responseMock);

        $this->ioMock = Mockery::mock(IOInterface::class);
        $this->tagsManger = new LegacyTagsManager(
            $this->ioMock,
            $downloaderMock
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $path = __DIR__ . '/tests/Prefetcher/https---flex.symfony.com';

        $this->delete($path);
        @\rmdir($path);
    }

    public function testHasProvider(): void
    {
        $count = 0;

        $this->tagsManger->addConstraint('symfony/security-guard', '>=4.1');

        foreach ($this->downloadFileList as $file) {
            if ($this->tagsManger->hasProvider($file)) {
                $count++;
            }
        }

        self::assertSame(2, $count);
    }

    public function testReset(): void
    {
        $count = 0;

        $this->tagsManger->addConstraint('symfony/security-guard', '>=4.1');
        $this->tagsManger->reset();

        foreach ($this->downloadFileList as $file) {
            if ($this->tagsManger->hasProvider($file)) {
                $count++;
            }
        }

        self::assertSame(0, $count);
    }

    public function testRemoveLegacyTagsWithoutDataPackages(): void
    {
        self::assertSame([], $this->tagsManger->removeLegacyTags([]));
    }

    public function testRemoveLegacyTagsWithSymfony(): void
    {
        $this->tagsManger->addConstraint('symfony/symfony', '>=3.4');

        $originalData = \json_decode((string) \file_get_contents($this->downloadFileList['symfony$symfony']), true);

        $this->ioMock->shouldReceive('writeError')
            ->with(\sprintf('<info>Restricting packages listed in [%s] to [%s]</info>', 'symfony/symfony', '>=3.4'));

        $data = $this->tagsManger->removeLegacyTags($originalData);

        self::assertNotSame($originalData['packages'], $data['packages']);
    }

    public function testRemoveLegacyTagsSkipIfNoProviderFound(): void
    {
        $originalData = \json_decode((string) \file_get_contents($this->downloadFileList['codeigniter$framework']), true);

        self::assertSame($originalData, $this->tagsManger->removeLegacyTags($originalData));
    }

    public function testRemoveLegacyTagsWithCakePHP(): void
    {
        $originalData = \json_decode((string) \file_get_contents($this->downloadFileList['cakephp$cakephp']), true);

        $this->tagsManger->addConstraint('cakephp/cakephp', '>=3.5');

        $this->ioMock->shouldReceive('writeError')
            ->with(\sprintf('<info>Restricting packages listed in [%s] to [%s]</info>', 'cakephp/cakephp', '>=3.5'));

        $data = $this->tagsManger->removeLegacyTags($originalData);

        self::assertNotSame($originalData['packages'], $data['packages']);
    }

    /**
     * @dataProvider provideRemoveLegacyTagsCases
     *
     * @param array<string, array<int|string, array<int|string, array>>> $expected
     * @param array<string, array<int|string, array<int|string, array>>> $packages
     */
    public function testRemoveLegacyTags(array $expected, array $packages, string $symfonyRequire): void
    {
        $this->tagsManger->addConstraint('symfony/http-foundation', $symfonyRequire);

        $this->ioMock->shouldReceive('writeError');

        self::assertSame(['packages' => $expected], $this->tagsManger->removeLegacyTags(['packages' => $packages]));
    }

    /**
     * @return Generator<int|string, mixed[]>>
     */
    public static function provideRemoveLegacyTagsCases(): iterable
    {
        yield 'no-symfony/http-foundation' => [[123], [123], '~1'];

        $branchAlias = static function (string $versionAlias): array {
            return [
                'extra' => [
                    'branch-alias' => [
                        'dev-master' => $versionAlias . '-dev',
                    ],
                ],
            ];
        };

        $packages = [
            'foo/unrelated' => [
                '1.0.0' => [],
            ],
            'symfony/http-foundation' => [
                '3.3.0' => [
                    'version_normalized' => '3.3.0.0',
                    'replace' => ['symfony/foo' => 'self.version'],
                ],
                '3.4.0' => [
                    'version_normalized' => '3.4.0.0',
                    'replace' => ['symfony/foo' => 'self.version'],
                ],
                'dev-master' => $branchAlias('3.5') + [
                    'replace' => ['symfony/foo' => 'self.version'],
                ],
            ],
            'symfony/foo' => [
                '3.3.0' => ['version_normalized' => '3.3.0.0'],
                '3.4.0' => ['version_normalized' => '3.4.0.0'],
                'dev-master' => $branchAlias('3.5'),
            ],
        ];

        yield 'empty-intersection-ignores' => [$packages, $packages, '~2.0'];

        yield 'empty-intersection-ignores2' => [$packages, $packages, '~4.0'];

        $expected = $packages;

        unset($expected['symfony/http-foundation']['3.3.0'], $expected['symfony/foo']['3.3.0']);

        yield 'non-empty-intersection-filters' => [$expected, $packages, '~3.4'];

        unset($expected['symfony/http-foundation']['3.4.0'], $expected['symfony/foo']['3.4.0']);

        yield 'master-only' => [$expected, $packages, '~3.5'];

        $packages = [
            'symfony/http-foundation' => [
                '2.8.0' => [
                    'version_normalized' => '2.8.0.0',
                    'replace' => [
                        'symfony/legacy' => 'self.version',
                        'symfony/foo' => 'self.version',
                    ],
                ],
            ],
            'symfony/legacy' => [
                '2.8.0' => ['version_normalized' => '2.8.0.0'],
                'dev-master' => $branchAlias('2.8'),
            ],
        ];

        yield 'legacy-are-not-filtered' => [$packages, $packages, '~3.0'];

        $packages = [
            'symfony/http-foundation' => [
                '2.8.0' => [
                    'version_normalized' => '2.8.0.0',
                    'replace' => [
                        'symfony/foo' => 'self.version',
                        'symfony/new' => 'self.version',
                    ],
                ],
                'dev-master' => $branchAlias('3.0') + [
                    'replace' => [
                        'symfony/foo' => 'self.version',
                        'symfony/new' => 'self.version',
                    ],
                ],
            ],
            'symfony/foo' => [
                '2.8.0' => ['version_normalized' => '2.8.0.0'],
                'dev-master' => $branchAlias('3.0'),
            ],
            'symfony/new' => [
                'dev-master' => $branchAlias('3.0'),
            ],
        ];

        $expected = $packages;

        unset($expected['symfony/http-foundation']['dev-master'], $expected['symfony/foo']['dev-master']);

        yield 'master-is-filtered-only-when-in-range' => [$expected, $packages, '~2.8'];
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    /**
     * @return array<string, string>
     */
    private static function getVersions(): array
    {
        return \json_decode('{
  "lts": "4.4",
  "stable": "5.0",
  "next": "5.1",
  "previous": "4.3",
  "master": "5.1",
  "splits": {
    "symfony/amazon-mailer": [
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/asset": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/browser-kit": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/cache": [
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/class-loader": [
      "2.7",
      "2.8",
      "3.4"
    ],
    "symfony/config": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/console": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/css-selector": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/debug": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4"
    ],
    "symfony/debug-bundle": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/dependency-injection": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/doctrine-bridge": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/dom-crawler": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/dotenv": [
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/error-handler": [
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/event-dispatcher": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/expression-language": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/filesystem": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/finder": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/form": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/framework-bundle": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/google-mailer": [
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/http-client": [
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/http-foundation": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/http-kernel": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/inflector": [
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/intl": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/ldap": [
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/locale": [
      "2.7",
      "2.8"
    ],
    "symfony/lock": [
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/mailchimp-mailer": [
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/mailer": [
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/mailgun-mailer": [
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/messenger": [
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/mime": [
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/monolog-bridge": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/nexmo-notifier": [
      "5.0",
      "5.1"
    ],
    "symfony/notifier": [
      "5.0",
      "5.1"
    ],
    "symfony/options-resolver": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/postmark-mailer": [
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/process": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/property-access": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/property-info": [
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/proxy-manager-bridge": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/routing": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/security": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4"
    ],
    "symfony/security-acl": [
      "2.7"
    ],
    "symfony/security-bundle": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/security-core": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/security-csrf": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/security-guard": [
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/security-http": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/sendgrid-mailer": [
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/serializer": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/slack-notifier": [
      "5.0",
      "5.1"
    ],
    "symfony/stopwatch": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/string": [
      "5.0",
      "5.1"
    ],
    "symfony/swiftmailer-bridge": [
      "2.7",
      "2.8"
    ],
    "symfony/telegram-notifier": [
      "5.0",
      "5.1"
    ],
    "symfony/templating": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/translation": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/twig-bridge": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/twig-bundle": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/twilio-notifier": [
      "5.0",
      "5.1"
    ],
    "symfony/validator": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/var-dumper": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/var-exporter": [
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/web-link": [
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/web-profiler-bundle": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/web-server-bundle": [
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4"
    ],
    "symfony/workflow": [
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ],
    "symfony/yaml": [
      "2.7",
      "2.8",
      "3.4",
      "4.1",
      "4.2",
      "4.3",
      "4.4",
      "5.0",
      "5.1"
    ]
  }
}', true);
    }

    private function delete(string $path): void
    {
        \array_map(function (string $value): void {
            if (\is_dir($value)) {
                $this->delete($value);

                @\rmdir($value);
            } else {
                @\unlink($value);
            }
        }, (array) \glob($path . \DIRECTORY_SEPARATOR . '*'));
    }
}
