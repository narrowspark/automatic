<?php

declare(strict_types=1);

namespace Narrowspark\Automatic\Test\Fixture;

use Narrowspark\Automatic\Common\Generator\AbstractGenerator;

final class ConsoleFixtureGenerator extends AbstractGenerator
{
    /**
     * Returns the project type of the class.
     *
     * @return string
     */
    public function getSkeletonType(): string
    {
        return 'console';
    }

    /**
     * @return string[]
     */
    public function getDependencies(): array
    {
        return [
            'psr/log' => '^1.0.0',
        ];
    }

    /**
     * @return string[]
     */
    public function getDevDependencies(): array
    {
        return [];
    }

    /**
     * Returns all directories that should be generated.
     *
     * @return string[]
     */
    protected function getDirectories(): array
    {
        return [];
    }

    /**
     * Returns all files that should be generated.
     *
     * @return array
     */
    protected function getFiles(): array
    {
        return [
            __DIR__ . '/test.php' => 'test',
        ];
    }
}
