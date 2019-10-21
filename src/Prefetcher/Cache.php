<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Prefetcher;

use Composer\Cache as BaseComposerCache;
use Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager as LegacyTagsManagerContract;

/**
 * Ported from symfony flex, see original.
 *
 * @see https://github.com/symfony/flex/blob/master/src/Cache.php
 *
 * (c) Nicolas Grekas <p@tchwork.com>
 */
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
     *
     * @param \Narrowspark\Automatic\Prefetcher\Contract\LegacyTagsManager $tagsManager
     *
     * @return void
     */
    public function setTagsManager(LegacyTagsManagerContract $tagsManager): void
    {
        $this->tagsManager = $tagsManager;
    }

    /**
     * @param mixed $file
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
     *
     * @param array $data
     *
     * @return array
     */
    public function removeLegacyTags(array $data): array
    {
        if ($this->tagsManager === null) {
            return $data;
        }

        return $this->tagsManager->removeLegacyTags($data);
    }
}
