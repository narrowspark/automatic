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
        \file_put_contents(
            $this->fixturePath . $this->getFolderName() . \DIRECTORY_SEPARATOR . 'composer.json',
            '{
    "require": {
        "php": "^7.2",
        "ext-mbstring": "*",
        "narrowspark/automatic": "dev-master"
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}'
        );

        $process = $this->runComposer165Update();

        static::assertTrue($process->isSuccessful());
        static::assertFileExists($this->fixturePath . $this->getFolderName() . \DIRECTORY_SEPARATOR . 'automatic.lock');
    }

    protected function getPackageName(): string
    {
        return 'narrowspark/automatic';
    }
}
