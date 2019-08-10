<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Prefetcher;

use Composer\IO\IOInterface;
use Composer\Util\RemoteFilesystem;
use Narrowspark\Automatic\Prefetcher\ParallelDownloader;
use Narrowspark\Automatic\Test\Traits\ArrangeComposerClasses;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 */
final class ParallelDownloaderTest extends MockeryTestCase
{
    use ArrangeComposerClasses;

    /** @var \Narrowspark\Automatic\Prefetcher\ParallelDownloader */
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
        $this->assertCount(1, $this->parallelDownloader->getOptions());

        $this->assertArrayHasKey('ssl', $options);
        $this->assertCount(2, $options);
    }

    public function testGetLastHeaders(): void
    {
        $this->assertNull($this->parallelDownloader->getLastHeaders());
    }
}
