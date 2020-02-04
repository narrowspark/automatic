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

namespace Narrowspark\Automatic\Contract;

use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Contract\Resettable;

interface Configurator extends Resettable
{
    /**
     * Get all registered configurators.
     */
    public function getConfigurators(): array;

    /**
     * Add a new automatic configurator.
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException
     */
    public function add(string $name, string $configurator): void;

    /**
     * Check if configurator is registered.
     */
    public function has(string $name): bool;

    /**
     * Configure the application after the package settings.
     */
    public function configure(PackageContract $package): void;

    /**
     * Unconfigure the application after the package settings.
     */
    public function unconfigure(PackageContract $package): void;
}
