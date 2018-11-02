<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Installer;

use Composer\Composer;
use Composer\DependencyResolver\Pool;
use Composer\Factory;
use Composer\Installer as BaseInstaller;
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
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
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
     * A backup of the original composer.json content.
     *
     * @var string
     */
    protected $composerBackup;

    /**
     * Create a new ExtraDependencyInstaller instance.
     *
     * @param \Composer\Composer                              $composer
     * @param \Composer\IO\IOInterface                        $io
     * @param \Symfony\Component\Console\Input\InputInterface $input
     */
    public function __construct(Composer $composer, IOInterface $io, InputInterface $input)
    {
        $this->composer       = $composer;
        $this->io             = $io;
        $this->input          = $input;
        $this->jsonFile       = new JsonFile(Factory::getComposerFile());
        $this->composerBackup = (string) \file_get_contents($this->jsonFile->getPath());

        $this->rootPackage = $this->composer->getPackage();
        $this->stability   = $this->rootPackage->getMinimumStability() ?: 'stable';

        $pool = new Pool($this->stability);
        $pool->addRepository(
            new CompositeRepository(\array_merge([new PlatformRepository()], RepositoryFactory::defaultRepos($io)))
        );

        $this->versionSelector = new VersionSelector($pool);
        $this->localRepository = $this->composer->getRepositoryManager()->getLocalRepository();

        foreach ($this->localRepository->getPackages() as $package) {
            $this->installedPackages[\strtolower($package->getName())] = \ltrim($package->getPrettyVersion(), 'v');
        }
    }

    /**
     * Get configured installer instance.
     *
     * @codeCoverageIgnore
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
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException
     *
     * @return string
     */
    protected function findBestVersionForPackage(string $name): string
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
     * Update the root package require and dev-require.
     *
     * @param array $requires
     * @param array $devRequires
     * @param int   $type
     *
     * @return \Composer\Package\RootPackageInterface
     */
    protected function updateRootComposerJson(array $requires, array $devRequires, int $type): RootPackageInterface
    {
        $this->io->writeError('Updating root package');

        $this->updateRootPackageRequire($requires, $type);
        $this->updateRootPackageDevRequire($devRequires, $type);

        return $this->rootPackage;
    }

    /**
     * Manipulate root composer.json with the new packages and dump it.
     *
     * @param array $requires
     * @param array $devRequires
     * @param int   $type
     *
     * @throws \Exception happens in the JsonFile class
     *
     * @return void
     */
    protected function updateComposerJson(array $requires, array $devRequires, int $type): void
    {
        $this->io->writeError('Updating composer.json');

        if ($type === self::ADD) {
            $jsonManipulator = new JsonManipulator(\file_get_contents($this->jsonFile->getPath()));
            $sortPackages    = $this->composer->getConfig()->get('sort-packages') ?? false;

            foreach ($requires as $name => $version) {
                $jsonManipulator->addLink('require', $name, $version, $sortPackages);
            }

            foreach ($devRequires as $name => $version) {
                $jsonManipulator->addLink('require-dev', $name, $version, $sortPackages);
            }

            \file_put_contents($this->jsonFile->getPath(), $jsonManipulator->getContents());
        } elseif ($type === self::REMOVE) {
            $jsonFileContent = $this->jsonFile->read();

            foreach ($requires as $packageName => $version) {
                unset($jsonFileContent['require'][$packageName]);
            }

            foreach ($devRequires as $packageName => $version) {
                unset($jsonFileContent['require-dev'][$packageName]);
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
     * Get merged root requires and dev-requires.
     *
     * @return \Composer\Package\Link[]
     */
    protected function getRootRequires(): array
    {
        return \array_merge($this->rootPackage->getRequires(), $this->rootPackage->getDevRequires());
    }

    /**
     * Update the root required packages.
     *
     * @param array $packages
     * @param int   $type
     *
     * @return void
     */
    protected function updateRootPackageRequire(array $packages, int $type): void
    {
        $requires = $this->manipulateRootPackage(
            $packages,
            $type,
            $this->rootPackage->getRequires()
        );

        $this->rootPackage->setRequires($requires);
    }

    /**
     * Update the root dev-required packages.
     *
     * @param array $packages
     * @param int   $type
     *
     * @return void
     */
    protected function updateRootPackageDevRequire(array $packages, int $type): void
    {
        $devRequires = $this->manipulateRootPackage(
            $packages,
            $type,
            $this->rootPackage->getDevRequires()
        );

        $this->rootPackage->setDevRequires($devRequires);
    }

    /**
     * Manipulates the given requires with the new added packages.
     *
     * @param array $packages
     * @param int   $type
     * @param array $requires
     *
     * @return array
     */
    protected function manipulateRootPackage(array $packages, int $type, array $requires): array
    {
        if ($type === self::ADD) {
            foreach ($packages as $packageName => $version) {
                $requires[$packageName] = new Link(
                    '__root__',
                    $packageName,
                    (new VersionParser())->parseConstraints($version),
                    'relates to',
                    $version
                );
            }
        } elseif ($type === self::REMOVE) {
            foreach ($packages as $packageName => $version) {
                unset($requires[$packageName]);
            }
        }

        return $requires;
    }
}
