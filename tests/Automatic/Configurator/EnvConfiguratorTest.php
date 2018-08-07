<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Configurator;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\Configurator\EnvConfigurator;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 */
final class EnvConfiguratorTest extends MockeryTestCase
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
     * @var \Narrowspark\Automatic\Configurator\EnvConfigurator
     */
    private $configurator;

    /**
     * @var string
     */
    private $envDistPath;

    /**
     * @var string
     */
    private $envPath;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new Composer();
        $this->nullIo   = new NullIO();

        $this->configurator = new EnvConfigurator($this->composer, $this->nullIo, []);

        $this->envDistPath = \sys_get_temp_dir() . '/.env.dist';
        $this->envPath     = \sys_get_temp_dir() . '/.env';

        \touch($this->envDistPath);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        @\unlink($this->envDistPath);
        @\unlink($this->envPath);
    }

    public function testGetName(): void
    {
        static::assertSame('env', EnvConfigurator::getName());
    }

    public function testConfigure(): void
    {
        $package = new Package(
            'test',
            'fixtures/test',
            __DIR__,
            false,
            [
                'version'   => '1',
                'url'       => 'example.local',
                'type'      => 'library',
                'operation' => 'i',
                'env'       => [
                    'APP_ENV'         => 'test bar',
                    'APP_DEBUG'       => '0',
                    'APP_PARAGRAPH'   => "foo\n\"bar\"\\t",
                    'DATABASE_URL'    => 'mysql://root@127.0.0.1:3306/narrowspark?charset=utf8mb4&serverVersion=5.7',
                    'MAILER_URL'      => 'null://localhost',
                    'MAILER_USER'     => 'narrow',
                    '#1'              => 'Comment 1',
                    '#2'              => 'Comment 3',
                    '#TRUSTED_SECRET' => 's3cretf0rt3st"<>',
                    'APP_SECRET'      => 's3cretf0rt3st"<>',
                ],
            ]
        );

        $this->configurator->configure($package);

        $envContents = <<<'EOF'
###> fixtures/test ###
APP_ENV="test bar"
APP_DEBUG=0
APP_PARAGRAPH="foo\n\"bar\"\\t"
DATABASE_URL="mysql://root@127.0.0.1:3306/narrowspark?charset=utf8mb4&serverVersion=5.7"
MAILER_URL=null://localhost
MAILER_USER=narrow
# Comment 1
# Comment 3
#TRUSTED_SECRET="s3cretf0rt3st\"<>"
APP_SECRET="s3cretf0rt3st\"<>"
###< fixtures/test ###

EOF;

        // Skip on second call
        $this->configurator->configure($package);

        static::assertStringEqualsFile($this->envDistPath, $envContents);
        static::assertStringEqualsFile($this->envPath, $envContents);
    }

    public function testUnconfigure(): void
    {
        $envConfig = [
            'APP_ENV'         => 'test',
            'APP_DEBUG'       => '0',
            '#1'              => 'Comment 1',
            '#2'              => 'Comment 3',
            '#TRUSTED_SECRET' => 's3cretf0rt3st',
            'APP_SECRET'      => 's3cretf0rt3st',
        ];

        $package = new Package(
            'env2',
            'fixtures/env2',
            __DIR__,
            false,
            [
                'version'   => '1',
                'url'       => 'example.local',
                'type'      => 'library',
                'operation' => 'i',
                'env'       => $envConfig,
            ]
        );

        $this->configurator->configure($package);

        $envContents = <<<'EOF'
###> fixtures/env2 ###
APP_ENV=test
APP_DEBUG=0
# Comment 1
# Comment 3
#TRUSTED_SECRET=s3cretf0rt3st
APP_SECRET=s3cretf0rt3st
###< fixtures/env2 ###

EOF;
        static::assertStringEqualsFile($this->envDistPath, $envContents);
        static::assertStringEqualsFile($this->envPath, $envContents);

        $package = new Package(
            'env2',
            'fixtures/env2',
            __DIR__,
            false,
            [
                'version'   => '1',
                'url'       => 'example.local',
                'type'      => 'library',
                'operation' => 'i',
                'env'       => $envConfig,
            ]
        );

        $this->configurator->unconfigure($package);

        static::assertStringEqualsFile(
            $this->envDistPath,
            <<<'EOF'

EOF
        );
        static::assertStringEqualsFile(
            $this->envPath,
            <<<'EOF'

EOF
        );
    }
}
