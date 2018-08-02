<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Contract;

interface Package
{
    /**
     * Get the package name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the package version.
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Get the package url.
     *
     * @return string
     */
    public function getUrl(): string;

    /**
     * Get the package type.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Get the package operation.
     *
     * @return string
     */
    public function getOperation(): string;

    /**
     * Get the package.
     *
     * @return string
     */
    public function getPackagePath(): string;

    /**
     * Checks if configurator key exits in extra automatic config.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasConfiguratorKey(string $key): bool;

    /**
     * Returns the needed options for the right configurator.
     *
     * @param string $key
     *
     * @return array
     */
    public function getConfiguratorOptions(string $key): array;

    /**
     * Returns all requirements of the package.
     *
     * @return array
     */
    public function getRequires(): array;

    /**
     * Get a option value.
     *
     * @param string $key
     *
     * @return null|array|string
     */
    public function getOption(string $key);

    /**
     * Returns the package config for narrowspark.
     *
     * @return array
     */
    public function getOptions(): array;
}
