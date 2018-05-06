<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Prefetcher;

use Composer\IO\IOInterface;
use Narrowspark\Discovery\Prefetcher\ParallelDownloader;
use Narrowspark\Discovery\Test\Traits\ArrangeComposerClasses;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

class ParallelDownloaderTest extends MockeryTestCase
{
    use ArrangeComposerClasses;

    /**
     * @var \Narrowspark\Discovery\Prefetcher\ParallelDownloader
     */
    private $parallelDownloader;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->arrangeComposerClasses();

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Composer >=1.7 not found, downloads will happen in sequence', true, IOInterface::DEBUG);

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
        self::assertNull($this->parallelDownloader->getLastHeaders());
    }
}
