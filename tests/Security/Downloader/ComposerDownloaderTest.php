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

namespace Narrowspark\Automatic\Security\Test\Downloader;

use Narrowspark\Automatic\Security\Downloader\ComposerDownloader;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
final class ComposerDownloaderTest extends TestCase
{
    /** @var string */
    private const SECURITY_ADVISORIES_SHA = 'https://raw.githubusercontent.com/narrowspark/security-advisories/master/security-advisories-sha';

    /** @var \Narrowspark\Automatic\Security\Downloader\ComposerDownloader */
    private $downloader;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->downloader = new ComposerDownloader();
    }

    public function testDownload(): void
    {
        self::assertNotEmpty($this->downloader->download(self::SECURITY_ADVISORIES_SHA));
    }
}
