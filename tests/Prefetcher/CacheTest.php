<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Test\Prefetcher;

use Composer\IO\IOInterface;
use Narrowspark\Automatic\Prefetcher\Cache;
use Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager as LegacyTagsManagerContract;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 *
 * @small
 */
final class CacheTest extends MockeryTestCase
{
    /** @var \Composer\IO\IOInterface|\Mockery\MockInterface */
    private $ioMock;

    /** @var \Mockery\MockInterface|\Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager */
    private $legacyTagsManager;

    /** @var \Narrowspark\Automatic\Prefetcher\Cache */
    private $cache;

    /** @var string */
    private $path;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->path = __DIR__ . '/Fixture/Packagist';
        $this->ioMock = $this->mock(IOInterface::class);
        $this->legacyTagsManager = $this->mock(LegacyTagsManagerContract::class);
        $this->cache = new Cache($this->ioMock, $this->path);
    }

    public function testRemoveLegacyTags(): void
    {
        $this->legacyTagsManager->shouldReceive('removeLegacyTags')
            ->never();

        $this->cache->removeLegacyTags([]);

        $this->cache->setTagsManager($this->legacyTagsManager);

        $this->legacyTagsManager->shouldReceive('removeLegacyTags')
            ->once()
            ->with([]);

        $this->cache->removeLegacyTags([]);
    }

    public function testReadWithoutFoundFile(): void
    {
        $file = 'provider.json';

        $this->legacyTagsManager->shouldReceive('hasProvider')
            ->with($file)
            ->andReturn(false);

        $this->legacyTagsManager->shouldReceive('removeLegacyTags')
            ->never();

        $this->cache->setTagsManager($this->legacyTagsManager);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Reading ' . $this->path . '/' . $file . ' from cache', true, IOInterface::DEBUG);

        $this->cache->read($file);
    }

    public function testReadWithFoundFile(): void
    {
        $file = 'provider.json';

        $this->legacyTagsManager->shouldReceive('hasProvider')
            ->with($file)
            ->andReturn(true);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Reading ' . $this->path . '/' . $file . ' from cache', true, IOInterface::DEBUG);

        $this->legacyTagsManager->shouldReceive('removeLegacyTags')
            ->once();

        $this->cache->setTagsManager($this->legacyTagsManager);

        $this->cache->read($file);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods(bool $allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
