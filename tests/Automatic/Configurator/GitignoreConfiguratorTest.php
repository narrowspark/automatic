<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Configurator;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\Configurator\GitIgnoreConfigurator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class GitignoreConfiguratorTest extends TestCase
{
    /**
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * @var \Composer\IO\NullIo
     */
    private $nullIo;

    /**
     * @var \Narrowspark\Automatic\Configurator\GitignoreConfigurator
     */
    private $configurator;

    /**
     * @var string
     */
    private $gitignorePath;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new Composer();
        $this->nullIo   = new NullIO();

        $this->configurator = new GitIgnoreConfigurator($this->composer, $this->nullIo, ['public-dir' => 'public']);

        $this->gitignorePath = \sys_get_temp_dir() . '/.gitignore';

        \touch($this->gitignorePath);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        @\unlink($this->gitignorePath);
    }

    public function testGetName(): void
    {
        static::assertSame('gitignore', GitIgnoreConfigurator::getName());
    }

    public function testConfigureAndUnconfigure(): void
    {
        $package = new Package(
            'FooBundle',
            'Foo/Bundle',
            \sys_get_temp_dir(),
            [
                'version'    => '1',
                'url'        => 'example.local',
                'type'       => 'library',
                'operation'  => 'i',
                'gitignore'  => [
                    '.env',
                    '/%PUBLIC_DIR%/css/',
                ],
            ]
        );

        $gitignoreContents1 = <<<'EOF'
###> Foo/Bundle ###
.env
/public/css/
###< Foo/Bundle ###
EOF;

        $package2 = new Package(
            'BarBundle',
            'Bar/Bundle',
            \sys_get_temp_dir(),
            [
                'version'    => '1',
                'url'        => 'example.local',
                'type'       => 'library',
                'operation'  => 'i',
                'gitignore'  => [
                    '/var/',
                    '/vendor/',
                ],
            ]
        );

        $gitignoreContents2 = <<<'EOF'
###> Bar/Bundle ###
/var/
/vendor/
###< Bar/Bundle ###
EOF;

        $this->configurator->configure($package);

        static::assertStringEqualsFile($this->gitignorePath, "\n" . $gitignoreContents1 . "\n");

        $this->configurator->configure($package2);

        static::assertStringEqualsFile($this->gitignorePath, "\n" . $gitignoreContents1 . "\n\n" . $gitignoreContents2 . "\n");

        $this->configurator->configure($package);
        $this->configurator->configure($package2);

        static::assertStringEqualsFile($this->gitignorePath, "\n" . $gitignoreContents1 . "\n\n" . $gitignoreContents2 . "\n");

        $this->configurator->unconfigure($package);

        static::assertStringEqualsFile($this->gitignorePath, $gitignoreContents2 . "\n");

        $this->configurator->unconfigure($package2);

        static::assertStringEqualsFile($this->gitignorePath, '');
    }

    public function testUnconfigureWithNotFoundPackage(): void
    {
        $package = new Package(
            'FooBundle',
            'Foo/Bundle',
            \sys_get_temp_dir(),
            [
                'version'    => '1',
                'url'        => 'example.local',
                'type'       => 'library',
                'operation'  => 'i',
                'gitignore'  => [
                    '.env',
                    '/%PUBLIC_DIR%/css/',
                ],
            ]
        );

        $this->configurator->configure($package);

        $package = new Package(
            'BarBundle',
            'Bar/Bundle',
            \sys_get_temp_dir(),
            [
                'version'    => '1',
                'url'        => 'example.local',
                'type'       => 'library',
                'operation'  => 'i',
                'gitignore'  => [
                    '/var/',
                    '/vendor/',
                ],
            ]
        );

        $this->configurator->unconfigure($package);

        $gitignoreContents1 = <<<'EOF'
###> Foo/Bundle ###
.env
/public/css/
###< Foo/Bundle ###
EOF;
        static::assertStringEqualsFile($this->gitignorePath, "\n" . $gitignoreContents1 . "\n");
    }
}
