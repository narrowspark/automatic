<?php
declare(strict_types=1);
namespace Narrowspark\Discovery;

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
     * The package extra config for narrowspark.
     *
     * @var array
     */
    private $packageConfig;

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
     * @param array  $packageConfig
     */
    public function __construct(string $name, string $vendorDirPath, array $packageConfig)
    {
        $this->name       = $name;
        $this->vendorPath = $vendorDirPath;
        $this->version    = $packageConfig['version'];

        unset($packageConfig['version']);

        $this->packageConfig = $packageConfig;
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
    public function getPackagePath(): string
    {
        return \strtr($this->vendorPath . '/' . $this->name . '/', '\\', '/');
    }

    /**
     * {@inheritdoc}
     */
    public function hasConfiguratorKey(string $key): bool
    {
        return \array_key_exists($key, $this->packageConfig);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguratorOptions(string $key): array
    {
        if ($this->hasConfiguratorKey($key)) {
            return (array) $this->packageConfig[$key];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getExtraOptions(): array
    {
        return $this->packageConfig;
    }
}
