<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\ProcessExecutor;
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\ScriptExecutor;
use Narrowspark\Automatic\ScriptExtender\ScriptExtender;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 */
final class ScriptExecutorTest extends MockeryTestCase
{
    /**
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * @var \Composer\IO\IOInterface|\Mockery\MockInterface
     */
    private $ioMock;

    /**
     * @var \Composer\Util\ProcessExecutor|\Mockery\MockInterface
     */
    private $processExecutor;

    /**
     * @var \Narrowspark\Automatic\ScriptExecutor
     */
    private $scriptExecutor;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->composer        = new Composer();
        $this->ioMock          = $this->mock(IOInterface::class);
        $this->processExecutor = $this->mock(ProcessExecutor::class);

        $this->scriptExecutor = new ScriptExecutor($this->composer, $this->ioMock, $this->processExecutor, []);
    }

    public function testAddedExtenderThrowException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The class [stdClass] must implement the interface [Narrowspark\Automatic\Common\Contract\ScriptExtender].');

        $this->scriptExecutor->addExtender(\stdClass::class);
    }

    public function testAddedExtenderThrowExceptionOnExistendExtender(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Script executor extender with the name [script] already exists.');

        $this->scriptExecutor->addExtender(ScriptExtender::class);
        $this->scriptExecutor->addExtender(ScriptExtender::class);
    }
}
