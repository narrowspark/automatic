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

namespace Narrowspark\Automatic\Tests\Configurator;

use Composer\Composer;
use Composer\IO\NullIO;
use Exception;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\Configurator\GitIgnoreConfigurator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\Configurator\GitignoreConfigurator
 *
 * @medium
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

        $gitignoreContents = "###> Foo/Bundle ###\n";
        $gitignoreContents .= ".env\n";
        $gitignoreContents .= "/public/css/\n";
        $gitignoreContents .= '###< Foo/Bundle ###';

        $package2 = $this->arrangePackageWithConfig('Bar/Bundle', [
            '/var/',
            '/vendor/',
        ]);

        $gitignoreContents2 = "###> Bar/Bundle ###\n";
        $gitignoreContents2 .= "/var/\n";
        $gitignoreContents2 .= "/vendor/\n";
        $gitignoreContents2 .= '###< Bar/Bundle ###';

        $this->configurator->configure($package);

        self::assertStringEqualsFile($this->gitignorePath, "\n" . $gitignoreContents . "\n");

        $this->configurator->configure($package2);

        self::assertStringEqualsFile($this->gitignorePath, "\n" . $gitignoreContents . "\n\n" . $gitignoreContents2 . "\n");

        $this->configurator->configure($package);
        $this->configurator->configure($package2);

        self::assertStringEqualsFile($this->gitignorePath, "\n" . $gitignoreContents . "\n\n" . $gitignoreContents2 . "\n");

        $this->configurator->unconfigure($package);

        self::assertStringEqualsFile($this->gitignorePath, $gitignoreContents2 . "\n");

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

        $gitignoreContents = "###> Foo/Bundle ###\n";
        $gitignoreContents .= ".env\n";
        $gitignoreContents .= "/public/css/\n";
        $gitignoreContents .= '###< Foo/Bundle ###';

        self::assertStringEqualsFile($this->gitignorePath, "\n" . $gitignoreContents . "\n");
    }

    /**
     * @throws Exception
     */
    private function arrangePackageWithConfig(string $name, array $config): Package
    {
        $package = new Package($name, '1.0.0');
        $package->setConfig([ConfiguratorContract::TYPE => [GitIgnoreConfigurator::getName() => $config]]);

        return $package;
    }
}
