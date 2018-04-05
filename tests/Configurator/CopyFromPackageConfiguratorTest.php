<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Configurator;


use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Discovery\Configurator\CopyFromPackageConfigurator;
use Narrowspark\Discovery\Package;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

class CopyFromPackageConfiguratorTest extends MockeryTestCase
{
    /**
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * @var \Composer\IO\NullIo
     */
    private $ioMock;

    /**
     * @var \Narrowspark\Discovery\Configurator\CopyFromPackageConfigurator
     */
    private $configurator;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new Composer();
        $this->ioMock   = $this->mock(IOInterface::class);

        $this->configurator = new CopyFromPackageConfigurator($this->composer, $this->ioMock, []);
    }

    public function testCopyFileFromPackage()
    {
        $toFileName = 'copy_of_copy.txt';

        $package = new Package(
            'Fixtures',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'copy' => [
                        'copy.txt' => $toFileName
                    ],
                ],
            ]
        );

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Copying files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Created <fg=green>"copy_of_copy.txt"</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        $filePath = \sys_get_temp_dir() . '/' . $toFileName;

        self::assertTrue(\file_exists($filePath));

        \unlink($filePath);
    }

    public function testCopyADirWithFileFromPackage()
    {
        $toAndFromFileName = '/css/style.css';

        $package = new Package(
            'Fixtures',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'copy' => [
                        $toAndFromFileName => $toAndFromFileName
                    ],
                ],
            ]
        );

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Copying files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Created <fg=green>"/css/style.css"</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        $dirPath  = \sys_get_temp_dir() . '/css';
        $filePath = $dirPath . '/style.css';

        self::assertTrue(\is_dir($dirPath));
        self::assertTrue(\file_exists($filePath));

        \unlink($filePath);
        \rmdir($dirPath);
    }

    public function testTryCopyAFileThatIsNotFoundFromPackage()
    {
        $toFileName = 'notfound.txt';

        $package = new Package(
            'Fixtures',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'copy' => [
                        $toFileName => $toFileName
                    ],
                ],
            ]
        );

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Copying files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    <fg=red>Failed to create "notfound.txt"</>; Error message: Failed to copy "'. __DIR__ .'/Fixtures/notfound.txt" because file does not exist.'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        $filePath = \sys_get_temp_dir() . '/' . $toFileName;

        self::assertFalse(\file_exists($filePath));
    }

    public function testUnconfigureAFileFromPackage()
    {
        $toFileName = 'copy_of_copy.txt';

        $package = new Package(
            'Fixtures',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'copy' => [
                        'copy.txt' => $toFileName
                    ],
                ],
                Package::UNCONFIGURE => [
                    'copy' => [
                        'copy.txt' => $toFileName
                    ],
                ],
            ]
        );

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Copying files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Created <fg=green>"copy_of_copy.txt"</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Removing files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Removed <fg=green>"copy_of_copy.txt"</>'], true, IOInterface::VERBOSE);

        $this->configurator->unconfigure($package);
    }

    public function testUnconfigureADirWithFileFromPackage()
    {
        $toAndFromFileName = '/css/style.css';

        $package = new Package(
            'Fixtures',
            __DIR__,
            [
                'package_version'  => '1',
                Package::CONFIGURE => [
                    'copy' => [
                        $toAndFromFileName => $toAndFromFileName
                    ],
                ],
                Package::UNCONFIGURE => [
                    'copy' => [
                        $toAndFromFileName => $toAndFromFileName
                    ],
                ],
            ]
        );

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Copying files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Created <fg=green>"/css/style.css"</>'], true, IOInterface::VERBOSE);

        $this->configurator->configure($package);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Removing files'], true, IOInterface::VERBOSE);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with(['    Removed <fg=green>"/css/style.css"</>'], true, IOInterface::VERBOSE);

        $this->configurator->unconfigure($package);
    }

    /**
     * {@inheritdoc}
     */
    protected function assertPreConditions(): void
    {
        parent::assertPreConditions();

        $this->allowMockingNonExistentMethods(true);
    }
}
