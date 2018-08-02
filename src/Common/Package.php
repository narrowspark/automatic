<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Common;

use Narrowspark\Discovery\Common\Contract\Package as PackageContract;

final class Package implements PackageContract
{
    /**
     * The package name.
     *
     * @var string
     */
    private $name;

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
     * The package config from discovery.
     *
     * @var array
     */
    private $options;

    /**
     * The configurator config from discovery.
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
     * Create a new Package instance.
     *
     * @param string $name
     * @param string $vendorDirPath
     * @param array  $options
     */
    public function __construct(string $name, string $vendorDirPath, array $options)
    {
        $this->name       = $name;
        $this->vendorPath = $vendorDirPath;
        $this->version    = $options['version'];
        $this->url        = $options['url'] ?? '';
        $this->operation  = $options['operation'];
        $this->type       = $options['type'];
        $this->options    = $options;

        unset(
            $options['version'],
            $options['type'],
            $options['operation'],
            $options['url'],
            $options['extra-dependency-of'],
            $options['selected-question-packages'],
            $options['require']
        );

        $this->configuratorOptions = $options;
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
    public function getVersion(): string
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
        return \str_replace('\\', '/', $this->vendorPath . '/' . $this->name . '/');
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
        return isset($this->options['extra-dependency-of']);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequires(): array
    {
        return $this->options['require'] ?? [];
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
}
