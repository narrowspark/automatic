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

namespace Test;

use Narrowspark\Automatic\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;

final class Configurator implements ConfiguratorContract
{
    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'test';
    }

    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
    }
}
