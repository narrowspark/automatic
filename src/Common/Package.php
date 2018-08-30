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
     * The package version.
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
        $this->name          = \mb_strtolower($name);
        $this->prettyVersion = $prettyVersion;
        $this->created       = (new \DateTimeImmutable())->format(\DateTime::RFC3339);
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

        foreach ($packageData as $key => $date) {
            if ($date !== null && isset($keyToFunctionMappers[$key])) {
                $package->{$keyToFunctionMappers[$key]}($date);
            }
        }

        return $package;
    }

    /**
     * {@inheritdoc}
     */
    public function setName(string $name): PackageContract
    {
        $this->name = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrettyName(): string
    {
        return $this->prettyName;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrettyVersion(): ?string
    {
        return $this->prettyVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function setIsDev(bool $bool = true): PackageContract
    {
        $this->isDev = $bool;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isDev(): bool
    {
        return $this->isDev;
    }

    /**
     * {@inheritdoc}
     */
    public function setAutoload(array $autoload): PackageContract
    {
        $this->autoload = $autoload;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAutoload(): array
    {
        return $this->autoload;
    }

    /**
     * {@inheritdoc}
     */
    public function setUrl(string $url): PackageContract
    {
        $this->url = $url;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * {@inheritdoc}
     */
    public function setOperation(string $operation): PackageContract
    {
        $this->operation = $operation;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * {@inheritdoc}
     */
    public function setType(string $type): PackageContract
    {
        $this->type = $type;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function setParentName(string $name): PackageContract
    {
        $this->parentName = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParentName(): ?string
    {
        return $this->parentName;
    }

    /**
     * {@inheritdoc}
     */
    public function setRequires(array $requires): PackageContract
    {
        $this->requires = $requires;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequires(): array
    {
        return $this->requires;
    }

    /**
     * {@inheritdoc}
     */
    public function setConfig(array $configs): PackageContract
    {
        $this->configs = $configs;

        return $this;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getConfigs(): array
    {
        return $this->configs;
    }

    /**
     * {@inheritdoc}
     */
    public function setTime(string $time): PackageContract
    {
        $this->created = $time;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTime(): string
    {
        return $this->created;
    }

    /**
     * {@inheritdoc}
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
