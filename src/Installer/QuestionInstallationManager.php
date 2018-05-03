<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Installer;

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
use Narrowspark\Discovery\Common\Exception\InvalidArgumentException;
use Narrowspark\Discovery\Common\Exception\RuntimeException;
use Narrowspark\Discovery\OperationsResolver;
use Narrowspark\Discovery\Traits\GetGenericPropertyReaderTrait;
use Symfony\Component\Console\Input\InputInterface;

class QuestionInstallationManager
{
    use GetGenericPropertyReaderTrait;

    /**
     * @var int
     */
    private const ADD = 1;

    /**
     * @var int
     */
    private const REMOVE = 0;

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
    private $rootPackage;

    /**
     * The composer instance.
     *
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * A operations resolver instance.
     *
     * @var \Narrowspark\Discovery\OperationsResolver
     */
    private $operationsResolver;

    /**
     * A repository implementation.
     *
     * @var \Composer\Repository\WritableRepositoryInterface
     */
    private $localRepository;

    /**
     * A input implementation.
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    /**
     * All local installed packages.
     *
     * @var string[]
     */
    private $installedPackages;

    /**
     * Get the minimum stability.
     *
     * @var string
     */
    private $stability;

    /**
     * List of selected question packages to install.
     *
     * @var array
     */
    private $packagesToInstall = [];

    /**
     * Create a new ExtraDependencyInstaller instance.
     *
     * @param \Composer\Composer                              $composer
     * @param \Composer\IO\IOInterface                        $io
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Narrowspark\Discovery\OperationsResolver       $operationsResolver
     */
    public function __construct(Composer $composer, IOInterface $io, InputInterface $input, OperationsResolver $operationsResolver)
    {
        $this->composer           = $composer;
        $this->io                 = $io;
        $this->input              = $input;
        $this->operationsResolver = $operationsResolver;
        $this->jsonFile           = new JsonFile(Factory::getComposerFile());

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
     * Install selected extra dependencies.
     *
     * @param \Narrowspark\Discovery\Common\Contract\Package $package
     * @param array                                          $dependencies
     *
     * @throws \Narrowspark\Discovery\Common\Exception\RuntimeException
     * @throws \Narrowspark\Discovery\Common\Exception\InvalidArgumentException
     * @throws \Exception
     *
     * @return \Narrowspark\Discovery\Common\Contract\Package[]
     */
    public function install(PackageContract $package, array $dependencies): array
    {
        if (! $this->io->isInteractive() || \count($dependencies) === 0) {
            // Do nothing in no-interactive mode
            return [];
        }

        $rootPackages = [];

        foreach ($this->getRootRequires() as $link) {
            $rootPackages[\mb_strtolower($link->getTarget())] = (string) $link->getConstraint();
        }

        $oldInstallManager = $this->composer->getInstallationManager();

        $this->addDiscoveryInstallationManagerToComposer($oldInstallManager);

        foreach ($dependencies as $question => $options) {
            if (! \is_array($options) || \count($options) < 2) {
                throw new RuntimeException('You must provide at least two optional dependencies.');
            }

            foreach ($options as $packageName => $version) {
                // Check if package variable is a integer
                if (\is_int($packageName)) {
                    $packageName = $version;
                }

                // Package has been already prepared to be installed, skipping.
                // Package from this group has been found in root composer, skipping.
                if (isset($this->packagesToInstall[$packageName]) || isset($rootPackages[$packageName])) {
                    continue 2;
                }

                // Check if package is currently installed, if so, use installed constraint and skip question.
                if (isset($this->installedPackages[$packageName])) {
                    $version    = $this->installedPackages[$packageName];
                    $constraint = \mb_strpos($version, 'dev-') === false ? '^' . $version : $version;

                    $this->packagesToInstall[$packageName] = $constraint;

                    $this->io->write(sprintf(
                        'Added package <info>%s</info> to composer.json with constraint <info>%s</info>;'
                        . ' to upgrade, run <info>composer require %s:VERSION</info>',
                        $packageName,
                        $constraint,
                        $packageName
                    ));

                    continue 2;
                }
            }

            $packageName = $this->askDependencyQuestion($question, $options);
            $constraint  = $options[$packageName] ?? $this->findVersion($packageName);

            $this->io->writeError(\sprintf('Using version <info>%s</info> for <info>%s</info>', $constraint, $packageName));

            $this->packagesToInstall[$packageName] = $constraint;
        }

        if (\count($this->packagesToInstall) !== 0) {
            $this->updateComposerJson($this->packagesToInstall, self::ADD);

            $this->runInstaller(
                $this->updateRootComposerJson($this->packagesToInstall, self::ADD),
                \array_keys($this->packagesToInstall)
            );
        }

        $operations = $this->composer->getInstallationManager()->getOperations();

        // Revert to the old install manager.
        $this->composer->setInstallationManager($oldInstallManager);

        $this->operationsResolver->setParentPackageName($package->getName());

        return $this->operationsResolver->resolve($operations);
    }

    /**
     * Returns selected packages from questions.
     *
     * @return array
     */
    public function getPackagesToInstall(): array
    {
        return $this->packagesToInstall;
    }

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
    public function uninstall(PackageContract $package, array $dependencies): array
    {
        $oldInstallManager = $this->composer->getInstallationManager();

        $this->addDiscoveryInstallationManagerToComposer($oldInstallManager);

        $this->updateComposerJson(
            \array_merge($dependencies, $package->getOption('selected-question-packages') ?? []),
            self::REMOVE
        );

        if (\count($dependencies) !== 0) {
            $localPackages = $this->localRepository->getPackages();
            $whiteList     = \array_merge($package->getRequires(), $dependencies);

            foreach ($localPackages as $localPackage) {
                $mixedRequires = \array_merge($localPackage->getRequires(), $localPackage->getDevRequires());

                foreach ($whiteList as $whitelistPackageName) {
                    if (isset($mixedRequires[$whitelistPackageName])) {
                        unset($whiteList[$whitelistPackageName]);
                    }
                }
            }

            $this->runInstaller(
                $this->updateRootComposerJson($dependencies, self::REMOVE),
                $whiteList
            );
        }

        $operations = $this->composer->getInstallationManager()->getOperations();

        // Revert to the old install manager.
        $this->composer->setInstallationManager($oldInstallManager);

        $this->operationsResolver->setParentPackageName($package->getName());

        return $this->operationsResolver->resolve($operations);
    }

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
     * Build question and ask it.
     *
     * @param string $question
     * @param array  $packages
     *
     * @throws \Exception
     *
     * @return string
     */
    private function askDependencyQuestion(string $question, array $packages): string
    {
        $ask          = \sprintf('<question>%s</question>' . "\n", $question);
        $i            = 0;
        $packageNames = [];

        foreach ($packages as $packageName => $version) {
            if (\is_int($packageName)) {
                $packageName = $version;
            }

            $packageNames[] = $packageName;

            if ($packageName === $version) {
                $version = $this->findVersion($packageName);
            }

            $ask .= \sprintf('  [<comment>%d</comment>] %s%s' . "\n", $i, $packageName, ' : ' . $version);

            $i++;
        }

        $ask .= '  Make your selection: ';

        do {
            $package = $this->io->askAndValidate(
                $ask,
                function ($input) use ($packageNames) {
                    // @codeCoverageIgnoreStart
                    $input = \is_numeric($input) ? (int) \trim($input) : -1;

                    return $packageNames[$input] ?? null;
                    // @codeCoverageIgnoreEnd
                }
            );
        } while (! $package);

        return $package;
    }

    /**
     * Try to find the best version fot the package.
     *
     * @param string $name
     *
     * @throws \Narrowspark\Discovery\Common\Exception\InvalidArgumentException
     *
     * @return string
     */
    private function findVersion(string $name): string
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
    private function updateRootComposerJson(array $packages, int $type): RootPackageInterface
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
    private function updateComposerJson(array $packages, int $type): void
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
    private function runInstaller(RootPackageInterface $rootPackage, array $whitelistPackages): int
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
    private function addDiscoveryInstallationManagerToComposer(BaseInstallationManager $oldInstallManager): void
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
    private function getRootRequires(): array
    {
        return \array_merge($this->rootPackage->getRequires(), $this->rootPackage->getDevRequires());
    }
}
