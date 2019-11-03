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

use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Repository\ComposerRepository as BaseComposerRepository;
use Composer\Util\RemoteFilesystem;
use Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager as LegacyTagsManagerContract;
use function is_array;
use function preg_replace;

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
            $config->get('cache-repo-dir') . '/' . preg_replace('{[^a-z0-9.]}i', '-', $this->url),
            'a-z0-9.$'
        );
    }

    /**
     * Set a tags manager instance.
     *
     * @param \Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager $tagsManager
     *
     * @return void
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

        return is_array($data) ? $this->cache->removeLegacyTags($data) : $data;
    }
}
