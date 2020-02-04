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

namespace Narrowspark\Automatic\Test\Common\Downloader;

use Composer\IO\IOInterface;
use Composer\Util\RemoteFilesystem;
use Narrowspark\Automatic\Common\Downloader\ParallelDownloader;
use Narrowspark\Automatic\Test\Traits\ArrangeComposerClassesTrait;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Common\Downloader\ParallelDownloader
 *
 * @medium
 */
final class ParallelDownloaderTest extends MockeryTestCase
{
    use ArrangeComposerClassesTrait;

    /** @var \Narrowspark\Automatic\Common\Downloader\ParallelDownloader */
    private $parallelDownloader;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->arrangeComposerClasses();

        if (! \method_exists(RemoteFilesystem::class, 'getRemoteContents')) {
            $this->ioMock->shouldReceive('writeError')
                ->once()
                ->with('Composer >=1.7 not found, downloads will happen in sequence', true, IOInterface::DEBUG);
        }

        $this->parallelDownloader = new ParallelDownloader($this->ioMock, $this->configMock);
    }

    public function testOptions(): void
    {
        $this->parallelDownloader->setNextOptions(['test' => true]);

        $options = $this->parallelDownloader->getOptions();

        // reset to default after call
        self::assertCount(1, $this->parallelDownloader->getOptions());

        self::assertArrayHasKey('ssl', $options);
        self::assertCount(2, $options);
    }

    public function testGetLastHeaders(): void
    {
        self::assertCount(0, $this->parallelDownloader->getLastHeaders());
    }
}
