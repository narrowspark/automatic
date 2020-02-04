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

namespace Narrowspark\Automatic\Test\Fixture;

use Narrowspark\Automatic\Common\Generator\AbstractGenerator;

final class ConsoleFixtureGenerator extends AbstractGenerator
{
    /**
     * Returns the project type of the class.
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
     */
    protected function getFiles(): array
    {
        return [
            __DIR__ . '/test.php' => 'test',
        ];
    }
}
