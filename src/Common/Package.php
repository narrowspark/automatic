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
     * The package version.
     *
     * @var string
     */
    private $version;

    /**
     * The package type.
     *
     * @var string
     */
    private $type;

    /**
     * The package url.
     *
     * @var string
     */
    private $url;

    /**
     * The package operation.
     *
     * @var string
     */
    private $operation;

    /**
     * The package requires.
     *
     * @var array
     */
    private $requires = [];

    /**
     * The package config from automatic.
     *
     * @var array
     */
    private $options;

    /**
     * The configurator config from automatic.
     *
     * @var array
     */
    private $configuratorOptions;

    /**
     * Path to the composer vendor dir.
     *
     * @var string
     */
    private $vendorPath;

    /**
     * Check if this package is a dev require.
     *
     * @var bool
     */
    private $isDev;

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
     * @param null|string $vendorDirPath
     * @param bool     $isDev
     * @param string[] $options
     */
    public function __construct(string $name, ?string $version, array $options)
    {
        $this->prettyName = $name;
        $this->name       = \mb_strtolower($name);
        $this->version    = $version;
        $this->url        = $options['url'] ?? '';
        $this->operation  = $options['operation'];
        $this->type       = $options['type'];
        $this->options    = $options;
        $this->created    = (new \DateTimeImmutable())->format(\DateTime::RFC3339);

        unset(
            $options['version'],
            $options['type'],
            $options['operation'],
            $options['url'],
            $options['extraDependencyOf'],
            $options['selected-question-packages'],
            $options['require']
        );

        $this->configuratorOptions = $options;
    }

    /**
     * Set the package name.
     *
     * @param string $name
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
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
    public function isDev(): bool
    {
        return $this->isDev;
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
    public function getPrettyVersion(): string
    {
        return $this->version;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Set the composer operation type.
     *
     * @var string $operation
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public function setOperation(string $operation): PackageContract
    {
        $this->operation = $operation;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getPackagePath(): string
    {
        return \str_replace('\\', '/', $this->vendorPath . '/' . $this->prettyName . '/');
    }

    /**
     * {@inheritdoc}
     */
    public function hasConfiguratorKey(string $key): bool
    {
        return \array_key_exists($key, $this->configuratorOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguratorOptions(string $key): array
    {
        if ($this->hasConfiguratorKey($key)) {
            return (array) $this->configuratorOptions[$key];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function isExtraDependency(): bool
    {
        return isset($this->options['extraDependencyOf']);
    }

    /**
     * Set the required packages
     *
     * @param string[] $requires
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
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
    public function getOption(string $key)
    {
        return $this->options[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp(): string
    {
        return $this->created;
    }

    /**
     * {@inheritdoc}
     */
    public function toJson(): string
    {
        return \json_encode(\array_merge(
            [
                'name'         => $this->name,
                'prettyName'   => $this->prettyName,
                'packagePath'  => $this->getPackagePath(),
                'isDev'        => $this->isDev,
            ],
            $this->options,
            [
                'created'      => $this->created,
            ]
        ));
    }
}
