<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Configurator;

use Composer\Composer;
use Composer\DependencyResolver\Pool;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryFactory;
use Narrowspark\Discovery\Common\Configurator\AbstractConfigurator;
use Narrowspark\Discovery\Common\Contract\Package as PackageContract;
use Narrowspark\Discovery\Common\Exception\InvalidArgumentException;
use Narrowspark\Discovery\Discovery;

class DependencyConfigurator extends AbstractConfigurator
{
    /**
     * All local installed packages.
     *
     * @var string[]
     */
    private $installedPackages;

    /**
     * A VersionSelector instance.
     *
     * @var \Composer\Package\Version\VersionSelector
     */
    private $versionSelector;

    /**
     * Get the minimum stability.
     *
     * @var string
     */
    private $stability;

    /**
     * {@inheritdoc}
     */
    public function __construct(Composer $composer, IOInterface $io, array $options = [])
    {
        parent::__construct($composer, $io, $options);

        $this->stability = $composer->getPackage()->getMinimumStability() ?: 'stable';

        $pool = new Pool($this->stability);
        $pool->addRepository(
            new CompositeRepository(\array_merge([new PlatformRepository()], RepositoryFactory::defaultRepos($io)))
        );

        $this->versionSelector = new VersionSelector($pool);

        $localPackages = $composer->getRepositoryManager()->getLocalRepository()->getPackages();

        foreach ($localPackages as $package) {
            $this->installedPackages[$package->getName()] = $package->getPrettyVersion();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
        if (! $this->io->isInteractive()) {
            // Do nothing in no-interactive mode
            return;
        }
        $this->write('Installing extra dependencies');

        foreach ($package->getConfiguratorOptions('dependency') as $dependency => $settings) {

        }
    }

    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
        if (! $this->io->isInteractive()) {
            // Do nothing in no-interactive mode
            return;
        }

        foreach ($package->getConfiguratorOptions('dependency') as $dependency => $settings) {

        }
    }

    /**
     * @param string $name
     *
     * @throws \Narrowspark\Discovery\Common\Exception\InvalidArgumentException
     *
     * @return string
     */
    private function findBestVersionForPackage(string $name): string
    {
        // find the latest version allowed in this pool
        $package = $this->versionSelector->findBestCandidate($name, null, null, 'stable');

        if ($package === false) {
            throw new InvalidArgumentException(sprintf(
                'Could not find package %s at any version for your minimum-stability (%s).'
                . ' Check the package spelling or your minimum-stability.',
                $name,
                $this->stability
            ));
        }

        return $this->versionSelector->findRecommendedRequireVersion($package);
    }

    /**
     * Update root composer.json require.
     *
     * @param \Composer\Package\RootPackageInterface $rootPackage
     * @param array                                  $packages
     * @param string                                 $type
     *
     * @return \Composer\Package\RootPackageInterface
     */
    private function updateRootComposerJson(RootPackageInterface $rootPackage, array $packages, string $type): RootPackageInterface
    {
        $this->write('Updating root package');

        $requires = $rootPackage->getRequires();

        foreach ($packages as $name => $version) {
            if ($type === 'add') {
                $requires[$name] = new Link(
                    '__root__',
                    $name,
                    (new VersionParser())->parseConstraints($version),
                    'requires',
                    $version
                );
            } else {
                unset($requires[$name]);
            }
        }

        $rootPackage->setRequires($requires);

        return $rootPackage;
    }

    /**
     * @param array $packages
     *
     * @return void
     */
    private function updateComposerJson(array $packages): void
    {
        $this->write('<info>    Updating composer.json</info>');

        [$json, $manipulator] = Discovery::getComposerJsonFileAndManipulator();

        foreach ($packages as $name => $version) {
            $sortPackages = $this->composer->getConfig()->get('sort-packages') ?? false;

            $manipulator->addLink('require', $name, $version, $sortPackages);
        }

        \file_put_contents($json->getPath(), $manipulator->getContents());
    }

    /**
     * Create a installer instance.
     *
     * @param \Composer\Package\RootPackageInterface $package
     *
     * @return \Composer\Installer
     */
    private function createInstaller(RootPackageInterface $package): Installer
    {
        return new Installer(
            $this->io,
            $this->composer->getConfig(),
            $package,
            $this->composer->getDownloadManager(),
            $this->composer->getRepositoryManager(),
            $this->composer->getLocker(),
            $this->composer->getInstallationManager(),
            new EventDispatcher($this->composer, $this->io),
            $this->composer->getAutoloadGenerator()
        );
    }
}
