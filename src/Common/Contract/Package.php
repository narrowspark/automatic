<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Contract;

interface Package
{
    public const INSTALL_OPERATION = 'install';

    public const UNINSTALL_OPERATION = 'uninstall';

    public const UPDATE_OPERATION = 'update';

    /**
     * Get the package name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the pretty package name.
     *
     * @return string
     */
    public function getPrettyName(): string;

    /**
     * Get the package version.
     *
     * @return string
     */
    public function getPrettyVersion(): string;

    /**
     * Get the package url.
     *
     * @return null|string
     */
    public function getUrl(): ?string;

    /**
     * Get the package type.
     *
     * @return null|string
     */
    public function getType(): ?string;

    /**
     * Get the package operation.
     *
     * @return null|string
     */
    public function getOperation(): ?string;

    /**
     * Returns all requirements of the package.
     *
     * @return array
     */
    public function getRequires(): array;

    /**
     * Checks if key exits in extra automatic config.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasConfig(string $key): bool;

    /**
     * Get a automatic config value.
     *
     * @param string $key
     *
     * @return null|array|string
     */
    public function getConfig(string $key);

    /**
     * Returns the automatic package configs.
     *
     * @return array
     */
    public function getConfigs(): array;

    /**
     * Get name of the parent package.
     *
     * @return null|string
     */
    public function getParentName(): ?string;

    /**
     * Is the package a questionable requirement.
     *
     * @return bool
     */
    public function isQuestionableRequirement(): bool;

    /**
     * Returns the object creation timestamp.
     *
     * @return string
     */
    public function getTimestamp(): string;

    /**
     * Transforms the package object to a json string.
     *
     * @return string
     */
    public function toJson(): string;
}
