<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Contract;

use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Contract\Resettable;

interface Configurator extends Resettable
{
    /**
     * Get all registered configurators.
     *
     * @return array
     */
    public function getConfigurators(): array;

    /**
     * Add a new automatic configurator.
     *
     * @param string $name
     * @param string $configurator
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException
     *
     * @return void
     */
    public function add(string $name, string $configurator): void;

    /**
     * Check if configurator is registered.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * Configure the application after the package settings.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     *
     * @return void
     */
    public function configure(PackageContract $package): void;

    /**
     * Unconfigure the application after the package settings.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     *
     * @return void
     */
    public function unconfigure(PackageContract $package): void;
}
