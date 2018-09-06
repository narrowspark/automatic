<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Security;

use Narrowspark\Automatic\Security\Downloader;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DownloaderTest extends TestCase
{
    /**
     * @var string
     */
    private const SECURITY_ADVISORIES_SHA = 'https://raw.githubusercontent.com/narrowspark/security-advisories/master/security-advisories-sha';

    /**
     * @var \Narrowspark\Automatic\Security\Downloader
     */
    private $downloader;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->downloader = new Downloader();
    }

    public function testDownloadWithComposer(): void
    {
        static::assertNotEmpty($this->downloader->downloadWithComposer(self::SECURITY_ADVISORIES_SHA));
    }

    public function testDownloadWithCurl(): void
    {
        static::assertNotEmpty($this->downloader->downloadWithCurl(self::SECURITY_ADVISORIES_SHA));
    }
}
