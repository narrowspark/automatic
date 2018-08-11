<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\Configurator;

/**
 * @internal
 */
final class ConfiguratorTest extends AbstractConfiguratorTest
{
    /**
     * @var string
     */
    private $copyFileName;

    /**
     * @var string
     */
    private $copyPath;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->copyFileName = 'copy_of_copy.txt';
        $this->copyPath     = \sys_get_temp_dir() . '/' . $this->copyFileName;
    }

    public function testConfigureWithCopy(): void
    {
        $this->arrangeVendorDir();

        $package = $this->arrangeCopyPackage();

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Copying files'], true, IOInterface::VERBOSE);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Created <fg=green>"' . $this->copyFileName . '"</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        static::assertFileExists($this->copyPath);

        \unlink($this->copyPath);
    }

    public function testUnconfigureWithCopy(): void
    {
        $this->arrangeVendorDir();

        $package = $this->arrangeCopyPackage();

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Copying files'], true, IOInterface::VERBOSE);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Created <fg=green>"' . $this->copyFileName . '"</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        static::assertFileExists($this->copyPath);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Removing files'], true, IOInterface::VERBOSE);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Removed <fg=green>"' . $this->copyFileName . '"</>'], true, IOInterface::VERBOSE);

        $this->configurator->unconfigure($package);

        static::assertFileNotExists($this->copyPath);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    /**
     * @return \Narrowspark\Automatic\Common\Package
     */
    protected function arrangeCopyPackage(): Package
    {
        $package = new Package('Fixture/copy', '1.0');
        $package->setConfig([
            'copy' => [
                'copy.txt' => $this->copyFileName,
            ],
        ]);

        return $package;
    }

    /**
     * {@inheritdoc}
     */
    protected function getConfiguratorClass(): string
    {
        return Configurator::class;
    }

    private function arrangeVendorDir(): void
    {
        $this->composerMock->shouldReceive('getConfig->get')
            ->with('vendor-dir')
            ->andReturn(__DIR__);
    }
}
