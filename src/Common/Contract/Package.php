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

namespace Narrowspark\Automatic\Common\Contract;

interface Package
{
    public const INSTALL_OPERATION = 'install';

    public const UNINSTALL_OPERATION = 'uninstall';

    public const UPDATE_OPERATION = 'update';

    /**
     * Set the package name.
     */
    public function setName(string $name): self;

    /**
     * Get the package name.
     */
    public function getName(): string;

    /**
     * Get the pretty package name.
     */
    public function getPrettyName(): string;

    /**
     * Get the package version.
     */
    public function getPrettyVersion(): ?string;

    /**
     * Active this if the package is a dev-require.
     */
    public function setIsDev(bool $bool = true): self;

    /**
     * Check if the package is a dev requirement.
     */
    public function isDev(): bool;

    /**
     * Set the package autoload.
     */
    public function setAutoload(array $autoload): self;

    /**
     * Get the package autoload.
     */
    public function getAutoload(): array;

    /**
     * Set the package url.
     */
    public function setUrl(string $url): self;

    /**
     * Get the package url.
     */
    public function getUrl(): ?string;

    /**
     * Set the package type.
     */
    public function setType(string $type): self;

    /**
     * Get the package type.
     */
    public function getType(): ?string;

    /**
     * Set the composer operation type.
     */
    public function setOperation(string $operation): self;

    /**
     * Get the package operation.
     */
    public function getOperation(): ?string;

    /**
     * Set the required packages.
     *
     * @param string[] $requires
     */
    public function setRequires(array $requires): self;

    /**
     * Returns all requirements of the package.
     */
    public function getRequires(): array;

    /**
     * Set the composer extra automatic package configs.
     */
    public function setConfig(array $configs): self;

    /**
     * Checks if key exits in extra automatic config.
     */
    public function hasConfig(string $mainKey, ?string $name = null): bool;

    /**
     * Get a automatic config value.
     *
     * @return null|array|bool|string
     */
    public function getConfig(string $mainKey, ?string $name = null);

    /**
     * Returns the automatic package configs.
     */
    public function getConfigs(): array;

    /**
     * Set name of the parent package.
     */
    public function setParentName(string $name): self;

    /**
     * Get name of the parent package.
     */
    public function getParentName(): ?string;

    /**
     * Set the package time.
     *
     * @param string $time this \DateTime::RFC3339 format should be used
     */
    public function setTime(string $time): self;

    /**
     * Returns the object creation time.
     */
    public function getTime(): string;

    /**
     * Transforms the package object to a array.
     */
    public function toArray(): array;
}
