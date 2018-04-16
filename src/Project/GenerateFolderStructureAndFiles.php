<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Project;

use Composer\IO\IOInterface;
use Narrowspark\Discovery\Common\Traits\ExpandTargetDirTrait;
use Narrowspark\Discovery\Discovery;
use Symfony\Component\Filesystem\Filesystem;

final class GenerateFolderStructureAndFiles
{
    use ExpandTargetDirTrait;

    /**
     * Creates all need folders and files.
     *
     * @param array                    $options
     * @param string                   $projectType
     * @param \Composer\IO\IOInterface $io
     *
     * @return void
     */
    public static function create(array $options, string $projectType, IOInterface $io): void
    {
        $filesystem = new Filesystem();

        $filesystem->mkdir(self::expandTargetDir($options, '%CONFIG_DIR%'));

        $io->writeError('Config folder created', true, IOInterface::VERBOSE);

        self::createStorageFolders($options, $filesystem, $io);
        self::createTestFolders($options, $filesystem, $projectType, $io);
        self::createRoutesFolder($options, $filesystem, $projectType, $io);
        self::createResourcesFolders($options, $filesystem, $projectType, $io);
        self::createAppFolders($options, $filesystem, $projectType, $io);

        if (! isset($options['discovery_test']) && \file_exists('README.md')) {
            \unlink('README.md');
        }
    }

    /**
     * Create storage folders.
     *
     * @param array                                    $options
     * @param \Symfony\Component\Filesystem\Filesystem $filesystem
     * @param \Composer\IO\IOInterface                 $io
     *
     * @return void
     */
    private static function createStorageFolders(array $options, Filesystem $filesystem, IOInterface $io): void
    {
        $storagePath = self::expandTargetDir($options, '%STORAGE_DIR%');

        $storageFolders = [
            'storage'   => $storagePath,
            'logs'      => $storagePath . '/logs',
            'framework' => $storagePath . '/framework',
        ];

        $filesystem->mkdir($storageFolders);
        $filesystem->dumpFile($storageFolders['logs'] . '/.gitignore', "!.gitignore\n");
        $filesystem->dumpFile($storageFolders['framework'] . '/.gitignore', "down\n");

        $io->writeError('Storage folders created', true, IOInterface::VERBOSE);
    }

    /**
     * Create test folders.
     *
     * @param array                                    $options
     * @param \Symfony\Component\Filesystem\Filesystem $filesystem
     * @param string                                   $projectType
     * @param \Composer\IO\IOInterface                 $io
     *
     * @return void
     */
    private static function createTestFolders(array $options, Filesystem $filesystem, string $projectType, IOInterface $io): void
    {
        $testsPath = self::expandTargetDir($options, '%TESTS_DIR%');

        $testFolders = [
            'tests' => $testsPath,
            'unit'  => $testsPath . '/Unit',
        ];

        $phpunitContent = <<<'PHPUNITFIRSTCONTENT'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="http://schema.phpunit.de/7.0/phpunit.xsd"
    bootstrap="vendor/autoload.php"
    colors="true"
    verbose="true"
    failOnRisky="true"
    failOnWarning="true" 
>
    <php>
        <ini name="error_reporting" value="-1" />
        <ini name="intl.default_locale" value="en" />
        <ini name="intl.error_level" value="0" />
    </php>

    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>

PHPUNITFIRSTCONTENT;

        if (\in_array($projectType, [Discovery::FULL_PROJECT, Discovery::HTTP_PROJECT], true)) {
            $testFolders['feature'] = $testsPath . '/Feature';

            $phpunitContent .= "        <testsuite name=\"Feature\">\n            <directory suffix=\"Test.php\">./tests/Feature</directory>\n        </testsuite>\n";
        }

        $filesystem->mkdir($testFolders);

        $phpunitContent .= <<<'PHPUNITSECONDCONTENT'
    </testsuites>

    <filter>
        <whitelist processUncoveredFilesFromWhitelist="true">
            <directory suffix=".php">./</directory>
            <exclude>
                <directory>./vendor</directory>
                <directory>./tests</directory>
            </exclude>
        </whitelist>
    </filter>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="MAIL_DRIVER" value="array"/>
    </php>
</phpunit>
PHPUNITSECONDCONTENT;

        $filesystem->dumpFile($testFolders['tests'] . '/AbstractTestCase.php', "<?php\ndeclare(strict_types=1);\nnamespace Tests;\n\nuse PHPUnit\Framework\TestCase as BaseTestCase;\n\nabstract class AbstractTestCase extends BaseTestCase\n{\n}\n");

        if (! isset($options['discovery_test'])) {
            $filesystem->dumpFile('phpunit.xml', $phpunitContent);
        }

        $io->writeError('Tests folder created', true, IOInterface::VERBOSE);
    }

    /**
     * Create routes folder.
     *
     * @param array                                    $options
     * @param \Symfony\Component\Filesystem\Filesystem $filesystem
     * @param string                                   $projectType
     * @param \Composer\IO\IOInterface                 $io
     *
     * @return void
     */
    private static function createRoutesFolder(array $options, Filesystem $filesystem, string $projectType, IOInterface $io): void
    {
        $routesPath =self::expandTargetDir($options, '%ROUTES_DIR%');

        $filesystem->mkdir($routesPath);

        if (\in_array($projectType, [Discovery::FULL_PROJECT, Discovery::HTTP_PROJECT], true)) {
            $filesystem->dumpFile($routesPath . '/web.php', "<?php\ndeclare(strict_types=1);\nuse Viserio\Component\Routing\Proxy\Route;\n\nRoute::get('/', 'WelcomeController@index');");
            $filesystem->dumpFile($routesPath . '/api.php', "<?php\ndeclare(strict_types=1);\n\n");
        }

        if (\in_array($projectType, [Discovery::FULL_PROJECT, Discovery::CONSOLE_PROJECT], true)) {
            $filesystem->dumpFile($routesPath . '/console.php', "<?php\ndeclare(strict_types=1);\n\n");
        }

        $io->writeError('Routes folder created', true, IOInterface::VERBOSE);
    }

    /**
     * Create resources folders.
     *
     * @param array                                    $options
     * @param \Symfony\Component\Filesystem\Filesystem $filesystem
     * @param string                                   $projectType
     * @param \Composer\IO\IOInterface                 $io
     *
     * @return void
     */
    private static function createResourcesFolders(array $options, Filesystem $filesystem, string $projectType, IOInterface $io): void
    {
        if (\in_array($projectType, [Discovery::FULL_PROJECT, Discovery::HTTP_PROJECT], true)) {
            $resourcesPath =self::expandTargetDir($options, '%RESOURCES_DIR%');

            $testFolders = [
                'resources' => $resourcesPath,
                'views'     => $resourcesPath . '/views',
                'lang'      => $resourcesPath . '/lang',
            ];

            $filesystem->mkdir($testFolders);

            $io->writeError('Resources folder created', true, IOInterface::VERBOSE);
        }
    }

    /**
     * Create app folders.
     *
     * @param array                                    $options
     * @param \Symfony\Component\Filesystem\Filesystem $filesystem
     * @param string                                   $projectType
     * @param \Composer\IO\IOInterface                 $io
     *
     * @return void
     */
    private static function createAppFolders(array $options, Filesystem $filesystem, string $projectType, IOInterface $io): void
    {
        $appPath =self::expandTargetDir($options, '%APP_DIR%');

        $appFolders = [
            'app'      => $appPath,
            'provider' => $appPath . '/Provider',
        ];

        if (\in_array($projectType, [Discovery::FULL_PROJECT, Discovery::HTTP_PROJECT], true)) {
            $appFolders = \array_merge(
                $appFolders,
                [
                    'http'       => $appFolders['app'] . '/Http',
                    'controller' => $appFolders['app'] . '/Http/Controller',
                    'middleware' => $appFolders['app'] . '/Http/Middleware',
                ]
            );
            $filesystem->dumpFile($appFolders['controller'] . '/Controller.php', "<?php\ndeclare(strict_types=1);\nnamespace App\Http\Controller;\n\nuse Viserio\Component\Routing\Controller as BaseController;\n\nclass Controller extends BaseController\n{\n}\n");
        }

        if (\in_array($projectType, [Discovery::FULL_PROJECT, Discovery::CONSOLE_PROJECT], true)) {
            $appFolders['console'] = $appFolders['app'] . '/Console';
        }

        $filesystem->mkdir($appFolders);
    }
}
