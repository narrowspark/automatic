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

namespace Narrowspark\Automatic\Prefetcher;

use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Repository\ComposerRepository as BaseComposerRepository;
use Composer\Util\RemoteFilesystem;
use Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager as LegacyTagsManagerContract;

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
        ?EventDispatcher $eventDispatcher = null,
        ?RemoteFilesystem $rfs = null
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
     */
    public function setTagsManager(LegacyTagsManagerContract $tagsManager): void
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
