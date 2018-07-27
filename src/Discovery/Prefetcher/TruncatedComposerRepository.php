<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Prefetcher;

use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Repository\ComposerRepository as BaseComposerRepository;
use Composer\Util\RemoteFilesystem;

/**
 * Ported from symfony flex, see original.
 *
 * @see https://github.com/symfony/flex/blob/master/src/Cache.php
 *
 * (c) Nicolas Grekas <p@tchwork.com>
 */
class TruncatedComposerRepository extends BaseComposerRepository
{
    public function __construct(array $repoConfig, IOInterface $io, Config $config, EventDispatcher $eventDispatcher = null, RemoteFilesystem $rfs = null)
    {
        parent::__construct($repoConfig, $io, $config, $eventDispatcher, $rfs);
        $this->cache = new Cache($io, $config->get('cache-repo-dir') . '/' . \preg_replace('{[^a-z0-9.]}i', '-', $this->url), 'a-z0-9.$');
    }

    protected function fetchFile($filename, $cacheKey = null, $sha256 = null, $storeLastModifiedTime = false)
    {
        $data = parent::fetchFile($filename, $cacheKey, $sha256, $storeLastModifiedTime);

        return \is_array($data) ? $this->cache->removeLegacyTags($data) : $data;
    }
}
