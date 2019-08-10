<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Test\Downloader;

use Narrowspark\Automatic\Security\Downloader\CurlDownloader;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CurlDownloaderTest extends TestCase
{
    /** @var string */
    private const SECURITY_ADVISORIES_SHA = 'https://raw.githubusercontent.com/narrowspark/security-advisories/master/security-advisories-sha';

    /** @var \Narrowspark\Automatic\Security\Downloader\CurlDownloader */
    private $downloader;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->downloader = new CurlDownloader();
    }

    public function testDownload(): void
    {
        $this->assertNotEmpty($this->downloader->download(self::SECURITY_ADVISORIES_SHA));
    }
}
