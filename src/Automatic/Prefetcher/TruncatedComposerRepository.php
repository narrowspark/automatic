<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Prefetcher;

use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Repository\ComposerRepository as BaseComposerRepository;
use Composer\Util\RemoteFilesystem;
use Narrowspark\Automatic\LegacyTagsManager;

/**
 * Ported from symfony flex, see original.
 *
 * @see https://github.com/symfony/flex/blob/master/src/Cache.php
 *
 * (c) Nicolas Grekas <p@tchwork.com>
 */
final class TruncatedComposerRepository extends BaseComposerRepository
{
    /**
     * {@inheritdoc}
     */
    public function __construct(
        array $repoConfig,
        IOInterface $io,
        Config $config,
        EventDispatcher $eventDispatcher = null,
        RemoteFilesystem $rfs = null
    ) {
        parent::__construct($repoConfig, $io, $config, $eventDispatcher, $rfs);

        $this->cache = new Cache(
            $io,
            $config->get('cache-repo-dir') . '/' . \preg_replace('{[^a-z0-9.]}i', '-', $this->url),
            'a-z0-9.$'
        );
    }

    /**
     * Set a tags manager instance.
     *
     * @param \Narrowspark\Automatic\LegacyTagsManager $tagsManager
     *
     * @return void
     */
    public function setTagsManager(LegacyTagsManager $tagsManager): void
    {
        $this->cache->setTagsManager($tagsManager);
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchFile($filename, $cacheKey = null, $sha256 = null, $storeLastModifiedTime = false)
    {
        $data = parent::fetchFile($filename, $cacheKey, $sha256, $storeLastModifiedTime);

        return \is_array($data) ? $this->cache->removeLegacyTags($data) : $data;
    }
}
