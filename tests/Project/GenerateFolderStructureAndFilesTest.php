<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Project;

use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Narrowspark\Discovery\Project\GenerateFolderStructureAndFiles;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use Symfony\Component\Filesystem\Filesystem;

class GenerateFolderStructureAndFilesTest extends MockeryTestCase
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var \Composer\IO\IOInterface
     */
    private $ioMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->path   = __DIR__ . '/GenerateFolderStructureAndFilesTest';
        $this->ioMock = $this->mock(IOInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem())->remove($this->path);
    }

    public function testCreateWithFullProjectType(): void
    {
        $config = [
            'app-dir'       => $this->path . '/app',
            'config-dir'    => $this->path . '/config',
            'resources-dir' => $this->path . '/resources',
            'root-dir'      => $this->path,
            'routes-dir'    => $this->path . '/routes',
            'tests-dir'     => $this->path . '/tests',
            'storage-dir'   => $this->path . '/storage',
        ];

        GenerateFolderStructureAndFiles::create($config, 'full', new NullIO());

        foreach ($config as $dir) {
            self::assertDirectoryExists($dir);
        }

        self::assertFileExists($config['root-dir'] . '/phpunit.xml');

        self::assertDirectoryExists($config['app-dir'] . '/Console');
        self::assertDirectoryExists($config['app-dir'] . '/Provider');
        self::assertDirectoryExists($config['app-dir'] . '/Http/Middleware');
        self::assertFileExists($config['app-dir'] . '/Http/Controller/Controller.php');

        self::assertFileExists($config['routes-dir'] . '/api.php');
        self::assertFileExists($config['routes-dir'] . '/console.php');
        self::assertFileExists($config['routes-dir'] . '/web.php');

        self::assertDirectoryExists($config['resources-dir'] . '/lang');
        self::assertDirectoryExists($config['resources-dir'] . '/views');

        self::assertFileExists($config['storage-dir'] . '/framework/.gitignore');
        self::assertFileExists($config['storage-dir'] . '/logs/.gitignore');

        self::assertDirectoryExists($config['tests-dir'] . '/Feature');
        self::assertDirectoryExists($config['tests-dir'] . '/Unit');
        self::assertFileExists($config['tests-dir'] . '/AbstractTestCase.php');
    }

    public function testCreateWithConsoleProjectType(): void
    {
        $config = [
            'app-dir'       => $this->path . '/app',
            'config-dir'    => $this->path . '/config',
            'root-dir'      => $this->path,
            'routes-dir'    => $this->path . '/routes',
            'tests-dir'     => $this->path . '/tests',
            'storage-dir'   => $this->path . '/storage',
        ];

        GenerateFolderStructureAndFiles::create($config, 'console', new NullIO());

        foreach ($config as $dir) {
            self::assertDirectoryExists($dir);
        }

        self::assertFileExists($config['root-dir'] . '/phpunit.xml');

        self::assertDirectoryExists($config['app-dir'] . '/Console');
        self::assertDirectoryExists($config['app-dir'] . '/Provider');
        self::assertDirectoryNotExists($config['app-dir'] . '/Http/Middleware');
        self::assertFileNotExists($config['app-dir'] . '/Http/Controller/Controller.php');

        self::assertFileNotExists($config['routes-dir'] . '/api.php');
        self::assertFileExists($config['routes-dir'] . '/console.php');
        self::assertFileNotExists($config['routes-dir'] . '/web.php');

        self::assertDirectoryNotExists($this->path . '/resources/lang');
        self::assertDirectoryNotExists($this->path . '/resources/views');

        self::assertFileExists($config['storage-dir'] . '/framework/.gitignore');
        self::assertFileExists($config['storage-dir'] . '/logs/.gitignore');

        self::assertDirectoryNotExists($config['tests-dir'] . '/Feature');
        self::assertDirectoryExists($config['tests-dir'] . '/Unit');
        self::assertFileExists($config['tests-dir'] . '/AbstractTestCase.php');
    }

    public function testCreateWithHttpProjectType(): void
    {
        $config = [
            'app-dir'       => $this->path . '/app',
            'config-dir'    => $this->path . '/config',
            'resources-dir' => $this->path . '/resources',
            'root-dir'      => $this->path,
            'routes-dir'    => $this->path . '/routes',
            'tests-dir'     => $this->path . '/tests',
            'storage-dir'   => $this->path . '/storage',
        ];

        GenerateFolderStructureAndFiles::create($config, 'http', new NullIO());

        foreach ($config as $dir) {
            self::assertDirectoryExists($dir);
        }

        self::assertFileExists($config['root-dir'] . '/phpunit.xml');

        self::assertDirectoryNotExists($config['app-dir'] . '/Console');
        self::assertDirectoryExists($config['app-dir'] . '/Provider');
        self::assertDirectoryExists($config['app-dir'] . '/Http/Middleware');
        self::assertFileExists($config['app-dir'] . '/Http/Controller/Controller.php');

        self::assertFileExists($config['routes-dir'] . '/api.php');
        self::assertFileNotExists($config['routes-dir'] . '/console.php');
        self::assertFileExists($config['routes-dir'] . '/web.php');

        self::assertDirectoryExists($config['resources-dir'] . '/lang');
        self::assertDirectoryExists($config['resources-dir'] . '/views');

        self::assertFileExists($config['storage-dir'] . '/framework/.gitignore');
        self::assertFileExists($config['storage-dir'] . '/logs/.gitignore');

        self::assertDirectoryExists($config['tests-dir'] . '/Feature');
        self::assertDirectoryExists($config['tests-dir'] . '/Unit');
        self::assertFileExists($config['tests-dir'] . '/AbstractTestCase.php');
    }
}
