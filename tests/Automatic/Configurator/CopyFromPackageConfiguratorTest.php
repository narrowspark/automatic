<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Test\Configurator;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\Configurator\CopyFromPackageConfigurator;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 *
 * @small
 */
final class CopyFromPackageConfiguratorTest extends MockeryTestCase
{
    /** @var \Composer\Composer|\Mockery\MockInterface */
    private $composerMock;

    /** @var \Composer\IO\IOInterface|\Mockery\MockInterface */
    private $ioMock;

    /** @var \Narrowspark\Automatic\Configurator\CopyFromPackageConfigurator */
    private $configurator;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composerMock = $this->mock(Composer::class);
        $this->ioMock = $this->mock(IOInterface::class);

        $this->configurator = new CopyFromPackageConfigurator($this->composerMock, $this->ioMock, ['self-dir' => 'test']);
    }

    public function testGetName(): void
    {
        self::assertSame('copy', CopyFromPackageConfigurator::getName());
    }

    public function testCopyFileFromPackage(): void
    {
        $toFileName = 'copy_of_copy.txt';

        $package = $this->arrangePackageWithConfig('copy.txt', $toFileName);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Copying files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Created <fg=green>"copy_of_copy.txt"</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        $filePath = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . '' . $toFileName;

        self::assertFileExists($filePath);

        \unlink($filePath);
    }

    public function testCopyDirWithFileFromPackage(): void
    {
        $toAndFromFileName = \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . 'style.css';

        $package = $this->arrangePackageWithConfig($toAndFromFileName, $toAndFromFileName);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Copying files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Created <fg=green>"' . \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . 'style.css"</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        $dirPath = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'css';
        $filePath = $dirPath . \DIRECTORY_SEPARATOR . 'style.css';

        self::assertDirectoryExists($dirPath);
        self::assertFileExists($filePath);

        \unlink($filePath);
        \rmdir($dirPath);
    }

    public function testCopyDirFromPackage(): void
    {
        $toAndFromFileName = \DIRECTORY_SEPARATOR . 'css';

        $package = $this->arrangePackageWithConfig($toAndFromFileName, $toAndFromFileName);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Copying files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Created <fg=green>"' . \DIRECTORY_SEPARATOR . 'css"</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        $dirPath = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'css';
        $filePath = $dirPath . \DIRECTORY_SEPARATOR . 'style.css';

        self::assertDirectoryExists($dirPath);
        self::assertFileExists($filePath);

        \unlink($filePath);
        \rmdir($dirPath);
    }

    public function testTryCopyFileThatIsNotFoundFromPackage(): void
    {
        $toFileName = 'notfound.txt';

        $package = $this->arrangePackageWithConfig($toFileName, $toFileName);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Copying files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - <fg=red>Failed to find the from folder or file path for "' . __DIR__ . \DIRECTORY_SEPARATOR . 'Stub' . \DIRECTORY_SEPARATOR . 'stub' . \DIRECTORY_SEPARATOR . 'notfound.txt" in "' . $package->getName() . '" package</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        $filePath = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . '' . $toFileName;

        self::assertFileNotExists($filePath);
    }

    public function testUnconfigureAFileFromPackage(): void
    {
        $toFileName = 'copy_of_copy.txt';

        $package = $this->arrangePackageWithConfig('copy.txt', $toFileName);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Copying files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Created <fg=green>"copy_of_copy.txt"</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Removing files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Removed <fg=green>"copy_of_copy.txt"</>'], true, IOInterface::VERBOSE);

        $this->configurator->unconfigure($package);
    }

    public function testUnconfigureADirWithFileFromPackage(): void
    {
        $toAndFromFileName = \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . 'style.css';

        $package = $this->arrangePackageWithConfig($toAndFromFileName, $toAndFromFileName);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Copying files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Created <fg=green>"' . \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . 'style.css"</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Removing files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Removed <fg=green>"' . \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . 'style.css"</>'], true, IOInterface::VERBOSE);

        $this->configurator->unconfigure($package);

        $dirPath = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'css';

        self::assertDirectoryExists($dirPath);

        \rmdir($dirPath);
    }

    public function testUnconfigureWithAIOException(): void
    {
        $toAndFromFileName = \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . 'style.css';

        $package = $this->arrangePackageWithConfig($toAndFromFileName, $toAndFromFileName);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Copying files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Created <fg=green>"' . \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . 'style.css"</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        $filesystem = $this->mock(Filesystem::class);
        $filesystem->shouldReceive('remove')
            ->once()
            ->andThrow(IOException::class);

        $set = $this->setPrivate($this->configurator, 'filesystem');
        $set($filesystem);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Removing files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - <fg=red>Failed to remove "' . \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . 'style.css"</>; Error message: '], true, IOInterface::VERBOSE);

        $this->configurator->unconfigure($package);

        $dirPath = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'css';

        \unlink($dirPath . \DIRECTORY_SEPARATOR . 'style.css');

        self::assertDirectoryExists($dirPath);

        \rmdir($dirPath);
    }

    public function testCopyFileFromPackageWithConfig(): void
    {
        $toFileName = 'copy_of_copy.txt';

        $package = $this->arrangePackageWithConfig('copy.txt', '%SELF_DIR%' . \DIRECTORY_SEPARATOR . $toFileName);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Copying files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    - Created <fg=green>"test' . \DIRECTORY_SEPARATOR . 'copy_of_copy.txt"</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        $filePath = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'test' . \DIRECTORY_SEPARATOR . $toFileName;

        self::assertFileExists($filePath);

        \unlink($filePath);
        \rmdir(\sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'test' . \DIRECTORY_SEPARATOR);
    }

    /**
     * {@inheritdoc}
     */
    protected function assertPreConditions(): void
    {
        parent::assertPreConditions();

        $this->allowMockingNonExistentMethods(true);
    }

    /**
     * @param string $from
     * @param string $to
     *
     * @throws \Exception
     *
     * @return Package
     */
    private function arrangePackageWithConfig(string $from, string $to): Package
    {
        $this->composerMock->shouldReceive('getConfig->get')
            ->once()
            ->with('vendor-dir')
            ->andReturn(__DIR__);

        $package = new Package('Stub/stub', '1.0.0');
        $package->setConfig([ConfiguratorContract::TYPE => [CopyFromPackageConfigurator::getName() => [$from => $to]]]);

        return $package;
    }

    private function setPrivate($obj, $attribute): callable
    {
        $setter = function ($value) use ($attribute): void {
            $this->{$attribute} = $value;
        };

        return \Closure::bind($setter, $obj, \get_class($obj));
    }
}
