<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Prefetcher\Test;

use Composer\IO\IOInterface;
use Narrowspark\Automatic\Prefetcher\LegacyTagsManager;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 *
 * @small
 */
final class LegacyTagsManagerTest extends MockeryTestCase
{
    /** @var array */
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

        $pPath = __DIR__ . \DIRECTORY_SEPARATOR;

        $this->downloadFileList = [
            'cakephp$cakephp' => $pPath . \DIRECTORY_SEPARATOR . 'provider-cakephp$cakephp.json',
            'codeigniter$framework' => $pPath . \DIRECTORY_SEPARATOR . 'provider-codeigniter$framework.json',
            'symfony$security-guard' => $pPath . \DIRECTORY_SEPARATOR . 'provider-symfony$security-guard.json',
            'symfony$symfony' => $pPath . \DIRECTORY_SEPARATOR . 'provider-symfony$symfony.json',
            'zendframework$zend-diactoros' => $pPath . \DIRECTORY_SEPARATOR . 'provider-zendframework$zend-diactoros.json',
        ];

        $this->ioMock = $this->mock(IOInterface::class);
        $this->tagsManger = new LegacyTagsManager($this->ioMock);
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

        $originalData = \json_decode(\file_get_contents($this->downloadFileList['symfony$symfony']), true);

        $this->ioMock->shouldReceive('writeError')
            ->with(\sprintf('<info>Restricting packages listed in [%s] to [%s]</info>', 'symfony/symfony', '>=3.4'));

        $data = $this->tagsManger->removeLegacyTags($originalData);

        self::assertNotSame($originalData['packages'], $data['packages']);
    }

    public function testRemoveLegacyTagsSkipIfNoProviderFound(): void
    {
        $originalData = \json_decode(\file_get_contents($this->downloadFileList['codeigniter$framework']), true);

        self::assertSame($originalData, $this->tagsManger->removeLegacyTags($originalData));
    }

    public function testRemoveLegacyTagsWithCakePHP(): void
    {
        $originalData = \json_decode(\file_get_contents($this->downloadFileList['cakephp$cakephp']), true);

        $this->tagsManger->addConstraint('cakephp/cakephp', '>=3.5');

        $this->ioMock->shouldReceive('writeError')
            ->with(\sprintf('<info>Restricting packages listed in [%s] to [%s]</info>', 'cakephp/cakephp', '>=3.5'));

        $data = $this->tagsManger->removeLegacyTags($originalData);

        self::assertNotSame($originalData['packages'], $data['packages']);
    }

    /**
     * @dataProvider provideRemoveLegacyTagsCases
     *
     * @param array  $expected
     * @param array  $packages
     * @param string $symfonyRequire
     *
     * @return void
     */
    public function testRemoveLegacyTags(array $expected, array $packages, string $symfonyRequire): void
    {
        $this->tagsManger->addConstraint('symfony/symfony', $symfonyRequire);

        $this->ioMock->shouldReceive('writeError');

        self::assertSame(['packages' => $expected], $this->tagsManger->removeLegacyTags(['packages' => $packages]));
    }

    /**
     * @return \Generator
     */
    public function provideRemoveLegacyTagsCases(): iterable
    {
        yield 'no-symfony/symfony' => [[123], [123], '~1'];

        $branchAlias = static function ($versionAlias) {
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
            'symfony/symfony' => [
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

        unset($expected['symfony/symfony']['3.3.0'], $expected['symfony/foo']['3.3.0']);

        yield 'non-empty-intersection-filters' => [$expected, $packages, '~3.4'];

        unset($expected['symfony/symfony']['3.4.0'], $expected['symfony/foo']['3.4.0']);

        yield 'master-only' => [$expected, $packages, '~3.5'];

        $packages = [
            'symfony/symfony' => [
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
            'symfony/symfony' => [
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

        unset($expected['symfony/symfony']['dev-master'], $expected['symfony/foo']['dev-master']);

        yield 'master-is-filtered-only-when-in-range' => [$expected, $packages, '~2.8'];
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
