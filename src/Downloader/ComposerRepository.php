<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Downloader;

use Composer\Repository\ComposerRepository as BaseComposerRepository;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ComposerRepository extends BaseComposerRepository
{
    private $providerFiles;

    protected function loadProviderListings($data)
    {
        if (null !== $this->providerFiles) {
            parent::loadProviderListings($data);

            return;
        }

        $data = [$data];

        while ($data) {
            $this->providerFiles = [];

            foreach ($data as $data) {
                $this->loadProviderListings($data);
            }

            $loadingFiles = $this->providerFiles;
            $this->providerFiles = null;

            $data = [];

            $this->rfs->download($loadingFiles, function (...$args) use (&$data) {
                $data[] = $this->fetchFile(...$args);
            });
        }
    }

    protected function fetchFile($filename, $cacheKey = null, $sha256 = null, $storeLastModifiedTime = false)
    {
        if ($this->providerFiles !== null) {
            $this->providerFiles[] = [$filename, $cacheKey, $sha256, $storeLastModifiedTime];

            return [];
        }

        return parent::fetchFile($filename, $cacheKey, $sha256, $storeLastModifiedTime);
    }
}