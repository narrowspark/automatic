<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common;

use Narrowspark\Automatic\Common\Contract\Package as PackageContract;

final class Package implements PackageContract
{
    /**
     * The package name.
     *
     * @var string
     */
    private $name;

    /**
     * The pretty package name.
     *
     * @var string
     */
    private $prettyName;

    /**
     * The name of the parent package.
     *
     * @var null|string
     */
    private $parentName;

    /**
     * The package pretty version.
     *
     * @var null|string
     */
    private $prettyVersion;

    /**
     * The package type.
     *
     * @var null|string
     */
    private $type;

    /**
     * The package url.
     *
     * @var null|string
     */
    private $url;

    /**
     * The package operation.
     *
     * @var null|string
     */
    private $operation;

    /**
     * The package requires.
     *
     * @var array
     */
    private $requires = [];

    /**
     * The automatic package config.
     *
     * @var array
     */
    private $configs = [];

    /**
     * The package autoload values.
     *
     * @var array
     */
    private $autoload = [];

    /**
     * Check if this package is a dev require.
     *
     * @var bool
     */
    private $isDev = false;

    /**
     * Timestamp of the object creation.
     *
     * @var string
     */
    private $created;

    /**
     * Create a new Package instance.
     *
     * @param string      $name
     * @param null|string $prettyVersion
     *
     * @throws \Exception
     */
    public function __construct(string $name, ?string $prettyVersion)
    {
        $this->prettyName    = $name;
        $this->name          = \strtolower($name);
        $this->prettyVersion = $prettyVersion;
        $this->created       = (new \DateTimeImmutable())->format(\DateTime::RFC3339);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function setName(string $name): PackageContract
    {
        $this->name = $name;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getPrettyName(): string
    {
        return $this->prettyName;
    }

    /**
     * {@inheritDoc}
     */
    public function getParentName(): ?string
    {
        return $this->parentName;
    }

    /**
     * {@inheritDoc}
     */
    public function setParentName(string $name): PackageContract
    {
        $this->parentName = $name;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getPrettyVersion(): ?string
    {
        return $this->prettyVersion;
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * {@inheritDoc}
     */
    public function setType(string $type): PackageContract
    {
        $this->type = $type;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * {@inheritDoc}
     */
    public function setUrl(string $url): PackageContract
    {
        $this->url = $url;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * {@inheritDoc}
     */
    public function setOperation(string $operation): PackageContract
    {
        $this->operation = $operation;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getRequires(): array
    {
        return $this->requires;
    }

    /**
     * {@inheritDoc}
     */
    public function setRequires(array $requires): PackageContract
    {
        $this->requires = $requires;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigs(): array
    {
        return $this->configs;
    }

    /**
     * {@inheritDoc}
     */
    public function getAutoload(): array
    {
        return $this->autoload;
    }

    /**
     * {@inheritDoc}
     */
    public function setAutoload(array $autoload): PackageContract
    {
        $this->autoload = $autoload;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function isDev(): bool
    {
        return $this->isDev;
    }

    /**
     * {@inheritDoc}
     */
    public function setIsDev(bool $bool = true): PackageContract
    {
        $this->isDev = $bool;

        return $this;
    }

    /**
     * Create a automatic package from the lock data.
     *
     * @param string $name
     * @param array  $packageData
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public static function createFromLock(string $name, array $packageData): PackageContract
    {
        $keyToFunctionMappers = [
            'parent'          => 'setParentName',
            'is-dev'          => 'setIsDev',
            'url'             => 'setUrl',
            'operation'       => 'setOperation',
            'type'            => 'setType',
            'requires'        => 'setRequires',
            'automatic-extra' => 'setConfig',
            'autoload'        => 'setAutoload',
            'created'         => 'setTime',
        ];

        $package = new static($name, $packageData['version']);

        foreach ($packageData as $key => $data) {
            if ($data !== null && isset($keyToFunctionMappers[$key])) {
                $package->{$keyToFunctionMappers[$key]}($data);
            }
        }

        return $package;
    }

    /**
     * {@inheritDoc}
     */
    public function setConfig(array $configs): PackageContract
    {
        $this->configs = $configs;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hasConfig(string $mainKey, ?string $name = null): bool
    {
        $mainCheck = \array_key_exists($mainKey, $this->configs);

        if ($name === null) {
            return $mainCheck;
        }

        if ($mainCheck === true && \is_array($this->configs[$mainKey])) {
            return \array_key_exists($name, $this->configs[$mainKey]);
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(string $mainKey, ?string $name = null)
    {
        if (\array_key_exists($mainKey, $this->configs)) {
            if ($name === null) {
                return $this->configs[$mainKey];
            }

            if (\is_array($this->configs[$mainKey]) && \array_key_exists($name, $this->configs[$mainKey])) {
                return $this->configs[$mainKey][$name];
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function setTime(string $time): PackageContract
    {
        $this->created = $time;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getTime(): string
    {
        return $this->created;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return
            [
                'pretty-name'                        => $this->prettyName,
                'version'                            => $this->prettyVersion,
                'parent'                             => $this->parentName,
                'is-dev'                             => $this->isDev,
                'url'                                => $this->url,
                'operation'                          => $this->operation,
                'type'                               => $this->type,
                'requires'                           => $this->requires,
                'automatic-extra'                    => $this->configs,
                'autoload'                           => $this->autoload,
                'created'                            => $this->created,
            ];
    }
}
