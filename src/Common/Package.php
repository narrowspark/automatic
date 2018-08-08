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
     * @var string
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
     * List of automatic configurator config.
     *
     * @var array
     */
    private $configuratorConfigs = [];

    /**
     * List of selected questionable requirements.
     *
     * @var string[]
     */
    private $selectedQuestionableRequirements = [];

    /**
     * Check if this package is a dev require.
     *
     * @var bool
     */
    private $isDev = false;

    /**
     * Check if the package is a questionable requirement.
     *
     * @var bool
     */
    private $isQuestionableRequirement = false;

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
    public function getPrettyName(): string
    {
        return $this->prettyName;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrettyVersion(): string
    {
        return $this->prettyVersion;
    }

    /**
     * Active this if the package is a dev-require.
     *
     * @param bool $bool
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
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
     * Set the package url.
     *
     * @param string $url
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
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
     * Set the composer operation type.
     *
     * @var string
     *
     * @param string $operation
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
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * Set the package type.
     *
     * @var string
     *
     * @param string $type
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
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
     * Set name of the parent package.
     *
     * @param string $name
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
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
     * Set this if the information coming from the QuestionInstallationManager.
     *
     * @param bool $bool
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public function setIsQuestionableRequirement(bool $bool = true): PackageContract
    {
        $this->isQuestionableRequirement = $bool;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isQuestionableRequirement(): bool
    {
        return $this->isQuestionableRequirement;
    }

    /**
     * Set selected questionable requirements.
     *
     * @param array $selectedQuestionableRequirements
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public function setSelectedQuestionableRequirements(array $selectedQuestionableRequirements): PackageContract
    {
        $this->selectedQuestionableRequirements = $selectedQuestionableRequirements;

        return $this;
    }

    /**
     * Return the selected questionable requirements.
     *
     * @return string[]
     */
    public function getSelectedQuestionableRequirements(): array
    {
        return $this->selectedQuestionableRequirements;
    }

    /**
     * Set the required packages.
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
     * Set the composer extra automatic package configs.
     *
     * @param array $configs
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package
     */
    public function setConfig(array $configs): PackageContract
    {
        $this->configs = $configs;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasConfig(string $key): bool
    {
        return \array_key_exists($key, $this->configs);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(string $key)
    {
        return $this->configs[$key] ?? null;
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
    public function getTimestamp(): string
    {
        return $this->created;
    }

    /**
     * {@inheritdoc}
     */
    public function toJson(): string
    {
        return \json_encode([
            'name'                 => $this->name,
            'pretty-name'          => $this->prettyName,
            'version'              => $this->prettyVersion,
            'parent'               => $this->parentName,
            'is-dev'               => $this->isDev,
            'url'                  => $this->url,
            'operation'            => $this->operation,
            'type'                 => $this->type,
            'requires'             => $this->requires,
            'automatic-extra'      => $this->configs,
            'created'              => $this->created,
        ]);
    }
}
