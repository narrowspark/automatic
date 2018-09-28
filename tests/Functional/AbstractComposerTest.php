<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Functional\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
abstract class AbstractComposerTest extends TestCase
{
    /**
     * @var string
     */
    protected $fixturePath;

    /**
     * @var string
     */
    protected $testFolderPath;

    /**
     * @var string
     */
    protected $composer165Path;

    /**
     * @var string
     */
    protected $composer172Path;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem      = new Filesystem();
        $this->fixturePath     = __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture' . \DIRECTORY_SEPARATOR;
        $this->composer165Path = $this->fixturePath . 'Composer1-6-5' . \DIRECTORY_SEPARATOR . 'composer.phar';
        $this->composer172Path = $this->fixturePath . 'Composer1-7-2' . \DIRECTORY_SEPARATOR . 'composer.phar';
        $this->testFolderPath  = $this->fixturePath . $this->getFolderName();

        $this->filesystem->mkdir($this->testFolderPath);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // remove Test folders
        $this->filesystem->remove($this->testFolderPath);
    }

    abstract protected function getPackageName(): string;

    /**
     * @return \Symfony\Component\Process\Process
     */
    protected function runComposer165Update(): Process
    {
        return $this->runComposer($this->composer165Path, 'update');
    }

    /**
     * @return \Symfony\Component\Process\Process
     */
    protected function runComposer172Update(): Process
    {
        return $this->runComposer($this->composer172Path, 'update');
    }

    /**
     * @throws \ReflectionException
     *
     * @return string
     */
    protected function getFolderName(): string
    {
        $reflect = new \ReflectionClass(static::class);

        return \str_replace('Test', '', $reflect->getShortName());
    }

    /**
     * @param \Symfony\Component\Process\Process $process
     *
     * @return array
     */
    protected function getOutput(Process $process): array
    {
        $messages = [
            'stdout' => [],
            'stderr' => [],
        ];

        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                $messages['stdout'][] = $data;
            } else { // $process::ERR === $type
                $messages['stderr'][] = $data;
            }
        }

        return $messages;
    }

    /**
     * @param string $composerPharPath
     * @param string $composerCommand
     *
     * @return \Symfony\Component\Process\Process
     */
    private function runComposer(string $composerPharPath, string $composerCommand): Process
    {
        $vendor = $this->testFolderPath . \DIRECTORY_SEPARATOR . 'vendor';

        $process = new Process(
            \sprintf(
                'COMPOSER=%s && COMPOSER_VENDOR_DIR=%s php %s %s --working-dir="%s"',
                $this->testFolderPath . \DIRECTORY_SEPARATOR . 'composer.json',
                $vendor,
                $composerPharPath,
                $composerCommand,
                $this->testFolderPath
            )
        );
        $process->setTty(true);
        $process->run();

        // remove Test folders
        $this->filesystem->remove($vendor . \DIRECTORY_SEPARATOR . $this->getPackageName() . \DIRECTORY_SEPARATOR . 'tests');

        return $process;
    }
}
