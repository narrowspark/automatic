<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Installer;

use Composer\Composer;
use Composer\DependencyResolver\Pool;
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryFactory;
use Narrowspark\Discovery\Common\Exception\InvalidArgumentException;
use Narrowspark\Discovery\Common\Exception\RuntimeException;
use Narrowspark\Discovery\Discovery;
use Narrowspark\Discovery\Lock;
use Narrowspark\Discovery\Traits\GetGenericPropertyReaderTrait;
use Symfony\Component\Console\Input\InputInterface;

final class ExtraInstallationManager
{
    use GetGenericPropertyReaderTrait;

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
     * A VersionSelector instance.
     *
     * @var \Composer\Package\Version\VersionSelector
     */
    private $versionSelector;

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
     * A lock instance.
     *
     * @var \Narrowspark\Discovery\Lock
     */
    private $lock;

    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * A input implementation.
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    /**
     * Create a new ExtraDependencyInstaller instance.
     *
     * @param \Composer\Composer                              $composer
     * @param \Composer\IO\IOInterface                        $io
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Narrowspark\Discovery\Lock                     $lock
     */
    public function __construct(Composer $composer, IOInterface $io, InputInterface $input, Lock $lock)
    {
        $this->composer = $composer;
        $this->io       = $io;
        $this->input    = $input;
        $this->lock     = $lock;

        $this->rootPackage = $composer->getPackage();
        $this->stability   = $this->rootPackage->getMinimumStability() ?: 'stable';

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
     * Install selected extra dependencies.
     *
     * @param array $dependencies
     *
     * @throws \Narrowspark\Discovery\Common\Exception\RuntimeException
     *
     * @return array
     */
    public function install(array $dependencies): array
    {
        if (! $this->io->isInteractive()) {
            // Do nothing in no-interactive mode
            return [];
        }

        $reader = $this->getGenericPropertyReader();

        $oldInstallManager = $this->composer->getInstallationManager();
        $installers        = $reader($oldInstallManager, 'installers');

        $narrowsparkInstaller = new InstallationManager();

        foreach ($installers as $installer) {
            $narrowsparkInstaller->addInstaller($installer);
        }

        $this->composer->setInstallationManager($narrowsparkInstaller);

        $packagesToInstall = [];

        foreach ($dependencies as $question => $options) {
            if (! is_array($options) || count($options) < 2) {
                throw new RuntimeException('You must provide at least two optional dependencies.');
            }

            foreach ($options as $package => $version) {
                // Check if package variable is a integer
                if (\is_int($package)) {
                    $package = $version;
                }

                // Package has been already prepared to be installed, skipping.
                // Package from this group has been found in root composer, skipping.
                if (isset($packagesToInstall[$package]) || isset($this->rootPackage->getRequires()[$package]) || isset($this->rootPackage->getDevRequires()[$package])) {
                    continue 2;
                }

                // Check if package is currently installed, if so, use installed constraint and skip question.
                if (isset($this->installedPackages[$package])) {
                    $packagesToInstall[$package] = $this->installedPackages[$package];

                    continue 2;
                }
            }

            $package    = $this->askDependencyQuestion($question, $options);
            $constraint = $options[$package] ?? $constraint = $this->findVersion($package);

            $this->io->writeError(\sprintf('Using version <info>%s</info> for <info>%s</info>', $constraint, $package));

            $packagesToInstall[$package] = $constraint;
        }

        if (\count($packagesToInstall) !== 0) {
            $this->updateComposerJson($packagesToInstall);

            $this->runInstaller(
                $this->updateRootComposerJson($this->composer->getPackage(), $packagesToInstall, true),
                \array_keys($packagesToInstall)
            );
        }

        $operations = $this->composer->getInstallationManager()->getOperations();

        // Add the old install manager back.
        $this->composer->setInstallationManager($oldInstallManager);

        return $operations;
    }

    /**
     * Uninstall extra dependencies.
     *
     * @param array $dependencies
     *
     * @return void
     */
    public function uninstall(array $dependencies): void
    {
        if (! $this->io->isInteractive()) {
            // Do nothing in no-interactive mode
            return;
        }
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

        foreach ($packages as $name => $version) {
            if (\is_int($name)) {
                $name = $version;
            }

            $packageNames[] = $name;

            $ask .= \sprintf('  [<comment>%d</comment>] %s%s' . "\n", $i, $name, ($name !== $version ? ' : ' . $version : ''));

            $i++;
        }

//        if (false) {
//            $ask .= \sprintf("  [<comment>%d</comment>] skip\n", $i + 1);
//        }
        $ask .= '  Make your selection: ';

        do {
            $package = $this->io->askAndValidate(
                $ask,
                function ($input) use ($packageNames) {
                    $input = \is_numeric($input) ? (int) \trim($input) : -1;

                    return $packageNames[$input] ?? null;
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
     * @param bool                                   $add
     *
     * @return \Composer\Package\RootPackageInterface
     */
    private function updateRootComposerJson(RootPackageInterface $rootPackage, array $packages, bool $add): RootPackageInterface
    {
        $this->io->writeError('Updating root package');

        $requires = $rootPackage->getRequires();

        foreach ($packages as $name => $version) {
            if ($add) {
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
     * Manipulate root composer.json with the new packages and dump it.
     *
     * @param array $packages
     *
     * @return void
     */
    private function updateComposerJson(array $packages): void
    {
        $this->io->writeError('Updating composer.json');

        [$json, $manipulator] = Discovery::getComposerJsonFileAndManipulator();

        foreach ($packages as $name => $version) {
            $sortPackages = $this->composer->getConfig()->get('sort-packages') ?? false;

            $manipulator->addLink('require', $name, $version, $sortPackages);
        }

        \file_put_contents($json->getPath(), $manipulator->getContents());
    }

    /**
     * Install selected packages.
     *
     * @param \Composer\Package\RootPackageInterface $rootPackage
     * @param array                                  $packages
     *
     * @throws \Exception
     *
     * @return int
     */
    private function runInstaller(RootPackageInterface $rootPackage, array $packages)
    {
        $this->io->writeError('Running an update to install dependent packages');

        $config    = $this->composer->getConfig();
        $installer = new Installer(
            $this->io,
            $config,
            $rootPackage,
            $this->composer->getDownloadManager(),
            $this->composer->getRepositoryManager(),
            $this->composer->getLocker(),
            $this->composer->getInstallationManager(),
            $this->composer->getEventDispatcher(),
            $this->composer->getAutoloadGenerator()
        );

        $installer->disablePlugins();
        $installer->setUpdate();
        $installer->setOptimizeAutoloader($config->get('optimize-autoloader') ?? false);
        $installer->setDevMode(($this->input->hasOption('no-dev') ? ! $this->input->getOption('no-dev') : true));
        $installer->setRunScripts(false);
        $installer->setUpdateWhitelist($packages);

        return $installer->run();
    }
}
