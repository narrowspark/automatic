<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\Automatic\Tests\Configurator;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\Configurator\EnvConfigurator;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use function touch;
use function unlink;

/**
 * @internal
 *
 * @medium
 */
final class EnvConfiguratorTest extends MockeryTestCase
{
    /** @var \Composer\Composer */
    private $composer;

    /** @var \Composer\IO\NullIo */
    private $nullIo;

    /** @var \Narrowspark\Automatic\Configurator\EnvConfigurator */
    private $configurator;

    /** @var string */
    private $envDistPath;

    /** @var string */
    private $envPath;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new Composer();
        $this->nullIo = new NullIO();

        $this->configurator = new EnvConfigurator($this->composer, $this->nullIo, ['root-dir' => __DIR__]);

        $this->envPath = __DIR__ . '/.env';
        $this->envDistPath = $this->envPath . '.dist';

        touch($this->envDistPath);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        @unlink($this->envDistPath);
        @unlink($this->envPath);
    }

    public function testGetName(): void
    {
        self::assertSame('env', EnvConfigurator::getName());
    }

    public function testConfigure(): void
    {
        $package = new Package('fixtures/test', '1.0.0');
        $package->setConfig([
            ConfiguratorContract::TYPE => [
                EnvConfigurator::getName() => [
                    'APP_ENV' => 'test bar',
                    'APP_DEBUG' => '0',
                    'APP_PARAGRAPH' => "foo\n\"bar\"\\t",
                    'DATABASE_URL' => 'mysql://root@127.0.0.1:3306/narrowspark?charset=utf8mb4&serverVersion=5.7',
                    'MAILER_URL' => 'null://localhost',
                    'MAILER_USER' => 'narrow',
                    '#1' => 'Comment 1',
                    '#2' => 'Comment 3',
                    '#TRUSTED_SECRET' => 's3cretf0rt3st"<>',
                    'APP_SECRET' => 's3cretf0rt3st"<>',
                    'BOOL' => false,
                    'VALID_NUMBER_TRUE' => 1,
                ],
            ],
        ]);

        $this->configurator->configure($package);

        $envContents = "###> fixtures/test ###\n";
        $envContents .= "APP_ENV=\"test bar\"\n";
        $envContents .= "APP_DEBUG=0\n";
        $envContents .= 'APP_PARAGRAPH="foo\n\"bar\"\\\t"' . "\n";
        $envContents .= "DATABASE_URL=\"mysql://root@127.0.0.1:3306/narrowspark?charset=utf8mb4&serverVersion=5.7\"\n";
        $envContents .= "MAILER_URL=null://localhost\n";
        $envContents .= "MAILER_USER=narrow\n";
        $envContents .= "# Comment 1\n";
        $envContents .= "# Comment 3\n";
        $envContents .= "#TRUSTED_SECRET=\"s3cretf0rt3st\\\"<>\"\n";
        $envContents .= "APP_SECRET=\"s3cretf0rt3st\\\"<>\"\n";
        $envContents .= "BOOL=false\n";
        $envContents .= "VALID_NUMBER_TRUE=1\n";
        $envContents .= "###< fixtures/test ###\n";

        // Skip on second call
        $this->configurator->configure($package);

        self::assertStringEqualsFile($this->envDistPath, $envContents);
        self::assertStringEqualsFile($this->envPath, $envContents);
    }

    public function testUnconfigure(): void
    {
        $envConfig = [
            'APP_ENV' => 'test',
            'APP_DEBUG' => '0',
            '#1' => 'Comment 1',
            '#2' => 'Comment 3',
            '#TRUSTED_SECRET' => 's3cretf0rt3st',
            'APP_SECRET' => 's3cretf0rt3st',
        ];

        $package = new Package('fixtures/env2', '1.0.0');
        $package->setConfig([ConfiguratorContract::TYPE => [EnvConfigurator::getName() => $envConfig]]);

        $this->configurator->configure($package);

        $envContents = "###> fixtures/env2 ###\n";
        $envContents .= "APP_ENV=test\n";
        $envContents .= "APP_DEBUG=0\n";
        $envContents .= "# Comment 1\n";
        $envContents .= "# Comment 3\n";
        $envContents .= "#TRUSTED_SECRET=s3cretf0rt3st\n";
        $envContents .= "APP_SECRET=s3cretf0rt3st\n";
        $envContents .= "###< fixtures/env2 ###\n";

        self::assertStringEqualsFile($this->envDistPath, $envContents);
        self::assertStringEqualsFile($this->envPath, $envContents);

        $this->configurator->unconfigure($package);

        self::assertStringEqualsFile(
            $this->envDistPath,
            <<<'EOF'

EOF
        );
        self::assertStringEqualsFile(
            $this->envPath,
            <<<'EOF'

EOF
        );
    }
}
