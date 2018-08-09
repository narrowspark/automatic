<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Contract;

interface Package
{
    public const INSTALL_OPERATION = 'install';

    public const UNINSTALL_OPERATION = 'uninstall';

    public const UPDATE_OPERATION = 'update';

    /**
     * Set the package name.
     *
     * @param string $name
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public function setName(string $name): self;

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
     * @return null|string
     */
    public function getPrettyVersion(): ?string;

    /**
     * Active this if the package is a dev-require.
     *
     * @param bool $bool
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public function setIsDev(bool $bool = true): self;

    /**
     * Check if the package is a dev requirement.
     *
     * @return bool
     */
    public function isDev(): bool;

    /**
     * Set the package url.
     *
     * @param string $url
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public function setUrl(string $url): self;

    /**
     * Get the package url.
     *
     * @return null|string
     */
    public function getUrl(): ?string;

    /**
     * Set the package type.
     *
     * @param string $type
     *
     * @return self
     */
    public function setType(string $type): self;

    /**
     * Get the package type.
     *
     * @return null|string
     */
    public function getType(): ?string;

    /**
     * Set the composer operation type.
     *
     * @param string $operation
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public function setOperation(string $operation): self;

    /**
     * Get the package operation.
     *
     * @return null|string
     */
    public function getOperation(): ?string;

    /**
     * Set the required packages.
     *
     * @param string[] $requires
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public function setRequires(array $requires): self;

    /**
     * Returns all requirements of the package.
     *
     * @return array
     */
    public function getRequires(): array;

    /**
     * Set the composer extra automatic package configs.
     *
     * @param array $configs
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public function setConfig(array $configs): self;

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
     * Set name of the parent package.
     *
     * @param string $name
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public function setParentName(string $name): self;

    /**
     * Get name of the parent package.
     *
     * @return null|string
     */
    public function getParentName(): ?string;

    /**
     * Set the package time.
     *
     * @param string $time this \DateTime::RFC3339 format should be used
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public function setTime(string $time): self;

    /**
     * Returns the object creation time.
     *
     * @return string
     */
    public function getTime(): string;

    /**
     * Transforms the package object to a array.
     *
     * @return array
     */
    public function toArray(): array;
}
