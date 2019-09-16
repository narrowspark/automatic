<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Test\Configurator;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\Configurator\GitIgnoreConfigurator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
final class GitignoreConfiguratorTest extends TestCase
{
    /** @var \Composer\Composer */
    private $composer;

    /** @var \Composer\IO\NullIo */
    private $nullIo;

    /** @var \Narrowspark\Automatic\Configurator\GitignoreConfigurator */
    private $configurator;

    /** @var string */
    private $gitignorePath;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new Composer();
        $this->nullIo = new NullIO();

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
        self::assertSame('gitignore', GitIgnoreConfigurator::getName());
    }

    public function testConfigureAndUnconfigure(): void
    {
        $package = $this->arrangePackageWithConfig('Foo/Bundle', [
            '.env',
            '/%PUBLIC_DIR%/css/',
        ]);

        $gitignoreContents = '###> Foo/Bundle ###' . \PHP_EOL;
        $gitignoreContents .= '.env' . \PHP_EOL;
        $gitignoreContents .= '/public/css/' . \PHP_EOL;
        $gitignoreContents .= '###< Foo/Bundle ###';

        $package2 = $this->arrangePackageWithConfig('Bar/Bundle', [
            '/var/',
            '/vendor/',
        ]);

        $gitignoreContents2 = '###> Bar/Bundle ###' . \PHP_EOL;
        $gitignoreContents2 .= '/var/' . \PHP_EOL;
        $gitignoreContents2 .= '/vendor/' . \PHP_EOL;
        $gitignoreContents2 .= '###< Bar/Bundle ###';

        $this->configurator->configure($package);

        self::assertStringEqualsFile($this->gitignorePath, \PHP_EOL . $gitignoreContents . \PHP_EOL);

        $this->configurator->configure($package2);

        self::assertStringEqualsFile($this->gitignorePath, \PHP_EOL . $gitignoreContents . \PHP_EOL . \PHP_EOL . $gitignoreContents2 . \PHP_EOL);

        $this->configurator->configure($package);
        $this->configurator->configure($package2);

        self::assertStringEqualsFile($this->gitignorePath, \PHP_EOL . $gitignoreContents . \PHP_EOL . \PHP_EOL . $gitignoreContents2 . \PHP_EOL);

        $this->configurator->unconfigure($package);

        self::assertStringEqualsFile($this->gitignorePath, $gitignoreContents2 . \PHP_EOL);

        $this->configurator->unconfigure($package2);

        self::assertStringEqualsFile($this->gitignorePath, '');
    }

    public function testUnconfigureWithNotFoundPackage(): void
    {
        $package = $this->arrangePackageWithConfig('Foo/Bundle', [
            '.env',
            '/%PUBLIC_DIR%/css/',
        ]);

        $this->configurator->configure($package);

        $package = $this->arrangePackageWithConfig('Bar/Bundle', [
            '/var/',
            '/vendor/',
        ]);

        $this->configurator->unconfigure($package);

        $gitignoreContents = '###> Foo/Bundle ###' . \PHP_EOL;
        $gitignoreContents .= '.env' . \PHP_EOL;
        $gitignoreContents .= '/public/css/' . \PHP_EOL;
        $gitignoreContents .= '###< Foo/Bundle ###';

        self::assertStringEqualsFile($this->gitignorePath, \PHP_EOL . $gitignoreContents . \PHP_EOL);
    }

    /**
     * @param string $name
     * @param array  $config
     *
     * @throws \Exception
     *
     * @return Package
     */
    private function arrangePackageWithConfig(string $name, array $config): Package
    {
        $package = new Package($name, '1.0.0');
        $package->setConfig([ConfiguratorContract::TYPE => [GitIgnoreConfigurator::getName() => $config]]);

        return $package;
    }
}
