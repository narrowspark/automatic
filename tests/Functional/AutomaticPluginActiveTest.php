<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Functional\Test;

/**
 * @internal
 */
final class AutomaticPluginActiveTest extends AbstractComposerTest
{
    public function testAutomaticComposerPluginActive(): void
    {
        $process = $this->runComposer165Update();

        static::assertTrue($process->isSuccessful());
        static::assertFileExists($this->fixturePath . $this->getFolderName() . DIRECTORY_SEPARATOR . 'automatic.lock');
    }

    protected function getPackageName(): string
    {
        return 'narrowspark/automatic';
    }
}
