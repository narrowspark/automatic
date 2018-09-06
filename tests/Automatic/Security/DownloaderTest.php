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
    public function testGetSecurityAdvisories(): void
    {
        $d = new Downloader();

        $d->getSecurityAdvisories();
    }
}
