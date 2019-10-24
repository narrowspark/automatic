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

namespace Narrowspark\Automatic\Test\Fixture;

use Narrowspark\Automatic\Common\Contract\Generator\DefaultGenerator as DefaultGeneratorContract;
use Narrowspark\Automatic\Common\Generator\AbstractGenerator;

final class FrameworkDefaultFixtureGenerator extends AbstractGenerator implements DefaultGeneratorContract
{
    /**
     * Returns the project type of the class.
     *
     * @return string
     */
    public function getSkeletonType(): string
    {
        return 'framework';
    }

    /**
     * @return string[]
     */
    public function getDependencies(): array
    {
        return [];
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
        return [];
    }
}
