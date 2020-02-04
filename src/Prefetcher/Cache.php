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

use Composer\Cache as BaseComposerCache;
use Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager as LegacyTagsManagerContract;

final class Cache extends BaseComposerCache
{
    /**
     * A tags manager instance.
     *
     * @var null|\Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager
     */
    private $tagsManager;

    /**
     * Set a tags manager instance.
     */
    public function setTagsManager(LegacyTagsManagerContract $tagsManager): void
    {
        $this->tagsManager = $tagsManager;
    }

    /**
     * @param string $file
     *
     * @return bool|string
     */
    public function read($file)
    {
        $content = parent::read($file);

        if ($this->tagsManager !== null && \is_string($content) && $this->tagsManager->hasProvider($file) && \is_array($data = \json_decode($content, true))) {
            $content = \json_encode($this->removeLegacyTags($data));
        }

        return $content;
    }

    /**
     * Helper to remove legacy tags.
     */
    public function removeLegacyTags(array $data): array
    {
        if ($this->tagsManager === null) {
            return $data;
        }

        return $this->tagsManager->removeLegacyTags($data);
    }
}
