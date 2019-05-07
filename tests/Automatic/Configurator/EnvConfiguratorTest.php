<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test\Configurator;

use Composer\Composer;
use Composer\IO\NullIO;
use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
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
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new Composer();
        $this->nullIo   = new NullIO();

        $this->configurator = new EnvConfigurator($this->composer, $this->nullIo, ['root-dir' => __DIR__]);

        $this->envPath     = __DIR__ . '/.env';
        $this->envDistPath = $this->envPath . '.dist';

        \touch($this->envDistPath);
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        @\unlink($this->envDistPath);
        @\unlink($this->envPath);
    }

    public function testGetName(): void
    {
        $this->assertSame('env', EnvConfigurator::getName());
    }

    public function testConfigure(): void
    {
        $package = new Package('fixtures/test', '1.0.0');
        $package->setConfig([
            ConfiguratorContract::TYPE => [
                EnvConfigurator::getName() => [
                    'APP_ENV'           => 'test bar',
                    'APP_DEBUG'         => '0',
                    'APP_PARAGRAPH'     => "foo\n\"bar\"\\t",
                    'DATABASE_URL'      => 'mysql://root@127.0.0.1:3306/narrowspark?charset=utf8mb4&serverVersion=5.7',
                    'MAILER_URL'        => 'null://localhost',
                    'MAILER_USER'       => 'narrow',
                    '#1'                => 'Comment 1',
                    '#2'                => 'Comment 3',
                    '#TRUSTED_SECRET'   => 's3cretf0rt3st"<>',
                    'APP_SECRET'        => 's3cretf0rt3st"<>',
                    'BOOL'              => false,
                    'VALID_NUMBER_TRUE' => 1,
                ],
            ],
        ]);

        $this->configurator->configure($package);

        $envContents = '###> fixtures/test ###' . \PHP_EOL;
        $envContents .= 'APP_ENV="test bar"' . \PHP_EOL;
        $envContents .= 'APP_DEBUG=0' . \PHP_EOL;
        $envContents .= 'APP_PARAGRAPH="foo\n\"bar\"\\\t"' . \PHP_EOL;
        $envContents .= 'DATABASE_URL="mysql://root@127.0.0.1:3306/narrowspark?charset=utf8mb4&serverVersion=5.7"' . \PHP_EOL;
        $envContents .= 'MAILER_URL=null://localhost' . \PHP_EOL;
        $envContents .= 'MAILER_USER=narrow' . \PHP_EOL;
        $envContents .= '# Comment 1' . \PHP_EOL;
        $envContents .= '# Comment 3' . \PHP_EOL;
        $envContents .= '#TRUSTED_SECRET="s3cretf0rt3st\"<>"' . \PHP_EOL;
        $envContents .= 'APP_SECRET="s3cretf0rt3st\"<>"' . \PHP_EOL;
        $envContents .= 'BOOL=false' . \PHP_EOL;
        $envContents .= 'VALID_NUMBER_TRUE=1' . \PHP_EOL;
        $envContents .= '###< fixtures/test ###' . \PHP_EOL;

        // Skip on second call
        $this->configurator->configure($package);

        $this->assertStringEqualsFile($this->envDistPath, $envContents);
        $this->assertStringEqualsFile($this->envPath, $envContents);
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

        $package = new Package('fixtures/env2', '1.0.0');
        $package->setConfig([ConfiguratorContract::TYPE => [EnvConfigurator::getName() => $envConfig]]);

        $this->configurator->configure($package);

        $envContents = '###> fixtures/env2 ###' . \PHP_EOL;
        $envContents .= 'APP_ENV=test' . \PHP_EOL;
        $envContents .= 'APP_DEBUG=0' . \PHP_EOL;
        $envContents .= '# Comment 1' . \PHP_EOL;
        $envContents .= '# Comment 3' . \PHP_EOL;
        $envContents .= '#TRUSTED_SECRET=s3cretf0rt3st' . \PHP_EOL;
        $envContents .= 'APP_SECRET=s3cretf0rt3st' . \PHP_EOL;
        $envContents .= '###< fixtures/env2 ###' . \PHP_EOL;

        $this->assertStringEqualsFile($this->envDistPath, $envContents);
        $this->assertStringEqualsFile($this->envPath, $envContents);

        $this->configurator->unconfigure($package);

        $this->assertStringEqualsFile(
            $this->envDistPath,
            <<<'EOF'

EOF
        );
        $this->assertStringEqualsFile(
            $this->envPath,
            <<<'EOF'

EOF
        );
    }
}
