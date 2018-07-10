<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Common\Installer;

use Composer\Composer;
use Composer\DependencyResolver\Pool;
use Composer\Factory;
use Composer\Installer as BaseInstaller;
use Composer\Installer\InstallationManager as BaseInstallationManager;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryFactory;
use Narrowspark\Discovery\Common\Contract\Package as PackageContract;
use Narrowspark\Discovery\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Discovery\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;

abstract class AbstractInstallationManager
{
    use GetGenericPropertyReaderTrait;

    /**
     * @var int
     */
    protected const ADD = 1;

    /**
     * @var int
     */
    protected const REMOVE = 0;

    /**
     * A VersionSelector instance.
     *
     * @var \Composer\Package\Version\VersionSelector
     */
    protected $versionSelector;

    /**
     * A composer json file instance.
     *
     * @var \Composer\Json\JsonFile
     */
    protected $jsonFile;

    /**
     * A root package implementation.
     *
     * @var \Composer\Package\RootPackageInterface
     */
    protected $rootPackage;

    /**
     * The composer instance.
     *
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * A repository implementation.
     *
     * @var \Composer\Repository\WritableRepositoryInterface
     */
    protected $localRepository;

    /**
     * A input implementation.
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * All local installed packages.
     *
     * @var string[]
     */
    protected $installedPackages;

    /**
     * Get the minimum stability.
     *
     * @var string
     */
    protected $stability;

    /**
     * Create a new ExtraDependencyInstaller instance.
     *
     * @param \Composer\Composer                              $composer
     * @param \Composer\IO\IOInterface                        $io
     * @param \Symfony\Component\Console\Input\InputInterface $input
     */
    public function __construct(Composer $composer, IOInterface $io, InputInterface $input)
    {
        $this->composer = $composer;
        $this->io       = $io;
        $this->input    = $input;
        $this->jsonFile = new JsonFile(Factory::getComposerFile());

        $this->rootPackage = $this->composer->getPackage();
        $this->stability   = $this->rootPackage->getMinimumStability() ?: 'stable';

        $pool = new Pool($this->stability);
        $pool->addRepository(
            new CompositeRepository(\array_merge([new PlatformRepository()], RepositoryFactory::defaultRepos($io)))
        );

        $this->versionSelector = new VersionSelector($pool);
        $this->localRepository = $this->composer->getRepositoryManager()->getLocalRepository();

        foreach ($this->localRepository->getPackages() as $package) {
            $this->installedPackages[\mb_strtolower($package->getName())] = \ltrim($package->getPrettyVersion(), 'v');
        }
    }

    /**
     * Install extra dependencies.
     *
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
     * @param array                                          $dependencies
     *
     * @throws \Narrowspark\Discovery\Exception\RuntimeException
     * @throws \Narrowspark\Discovery\Exception\InvalidArgumentException
     * @throws \Exception
     *
     * @return \Narrowspark\Discovery\Common\Contract\Package[]
     */
    abstract public function install(PackageContract $package, array $dependencies): array;

    /**
     * Uninstall extra dependencies.
     *
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
     * @param array                                          $dependencies
     *
     * @throws \Exception
     *
     * @return \Narrowspark\Discovery\Common\Contract\Package[]
     */
    abstract public function uninstall(PackageContract $package, array $dependencies): array;

    /**
     * @codeCoverageIgnore
     *
     * Get configured installer instance.
     *
     * @return \Composer\Installer
     */
    protected function getInstaller(): BaseInstaller
    {
        return Installer::create($this->io, $this->composer, $this->input);
    }

    /**
     * Try to find the best version fot the package.
     *
     * @param string $name
     *
     * @throws \Narrowspark\Discovery\Exception\InvalidArgumentException
     *
     * @return string
     */
    protected function findVersion(string $name): string
    {
        // find the latest version allowed in this pool
        $package = $this->versionSelector->findBestCandidate($name, null, null, 'stable');

        if ($package === false) {
            throw new InvalidArgumentException(\sprintf(
                'Could not find package %s at any version for your minimum-stability (%s).'
                . ' Check the package spelling or your minimum-stability.',
                $name,
                $this->stability
            ));
        }

        return $this->versionSelector->findRecommendedRequireVersion($package);
    }

    /**
     * Update the root composer.json require.
     *
     * @param array $packages
     * @param int   $type
     *
     * @return \Composer\Package\RootPackageInterface
     */
    protected function updateRootComposerJson(array $packages, int $type): RootPackageInterface
    {
        $this->io->writeError('Updating root package');

        $requires = $this->rootPackage->getRequires();

        if ($type === self::ADD) {
            foreach ($packages as $packageName => $version) {
                $requires[$packageName] = new Link(
                    '__root__',
                    $packageName,
                    (new VersionParser())->parseConstraints($version),
                    'requires',
                    $version
                );
            }
        } elseif ($type === self::REMOVE) {
            foreach ($packages as $packageName => $version) {
                unset($requires[$packageName]);
            }
        }

        $this->rootPackage->setRequires($requires);

        return $this->rootPackage;
    }

    /**
     * Manipulate root composer.json with the new packages and dump it.
     *
     * @param array $packages
     * @param int   $type
     *
     * @throws \Exception happens in the JsonFile class
     *
     * @return void
     */
    protected function updateComposerJson(array $packages, int $type): void
    {
        $this->io->writeError('Updating composer.json');

        if ($type === self::ADD) {
            $jsonManipulator = new JsonManipulator(\file_get_contents($this->jsonFile->getPath()));

            foreach ($packages as $name => $version) {
                $sortPackages = $this->composer->getConfig()->get('sort-packages') ?? false;

                $jsonManipulator->addLink('require', $name, $version, $sortPackages);
            }

            \file_put_contents($this->jsonFile->getPath(), $jsonManipulator->getContents());
        } elseif ($type === self::REMOVE) {
            $jsonFileContent = $this->jsonFile->read();

            foreach ($packages as $packageName => $version) {
                unset($jsonFileContent['require'][$packageName]);
            }

            $this->jsonFile->write($jsonFileContent);
        }
    }

    /**
     * Install selected packages.
     *
     * @param \Composer\Package\RootPackageInterface $rootPackage
     * @param array                                  $whitelistPackages
     *
     * @throws \Exception
     *
     * @return int
     */
    protected function runInstaller(RootPackageInterface $rootPackage, array $whitelistPackages): int
    {
        $this->io->writeError('Running an update to install dependent packages');

        $this->composer->setPackage($rootPackage);

        $installer = $this->getInstaller();
        $installer->setUpdateWhitelist($whitelistPackages);

        return $installer->run();
    }

    /**
     * Adds a modified installation manager to composer.
     *
     * @param \Composer\Installer\InstallationManager $oldInstallManager
     *
     * @return void
     */
    protected function addDiscoveryInstallationManagerToComposer(BaseInstallationManager $oldInstallManager): void
    {
        $reader     = $this->getGenericPropertyReader();
        $installers = (array) $reader($oldInstallManager, 'installers');

        $narrowsparkInstaller = new InstallationManager();

        foreach ($installers as $installer) {
            $narrowsparkInstaller->addInstaller($installer);
        }

        $this->composer->setInstallationManager($narrowsparkInstaller);
    }

    /**
     * Get merged root requires and dev-requires.
     *
     * @return \Composer\Package\Link[]
     */
    protected function getRootRequires(): array
    {
        return \array_merge($this->rootPackage->getRequires(), $this->rootPackage->getDevRequires());
    }
}
