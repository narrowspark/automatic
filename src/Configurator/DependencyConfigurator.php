<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Configurator;

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
use Narrowspark\Discovery\Common\Configurator\AbstractConfigurator;
use Narrowspark\Discovery\Common\Contract\Package as PackageContract;
use Narrowspark\Discovery\Common\Exception\InvalidArgumentException;
use Narrowspark\Discovery\Common\Exception\RuntimeException;
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
     * Get the minimum stability.
     *
     * @var \Composer\Package\RootPackageInterface
     */
    private $rootPackage;

    /**
     * {@inheritdoc}
     */
    public function __construct(Composer $composer, IOInterface $io, array $options = [])
    {
        parent::__construct($composer, $io, $options);

        $this->rootPackage = $composer->getPackage();
        $this->stability = $this->rootPackage->getMinimumStability() ?: 'stable';

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

        $packagesToInstall = [];

        foreach ($package->getConfiguratorOptions('dependency') as $question => $options) {
            if (! is_array($options) || count($options) < 2) {
                throw new RuntimeException('You must provide at least two optional dependencies.');
            }

            foreach ($options as $package) {
                // Package has been already prepared to be installed, skipping.
                // Package from this group has been found in root composer, skipping.
                if (isset($packagesToInstall[$package]) || isset($this->rootPackage->getRequires()[$package]) || isset($this->rootPackage->getDevRequires()[$package])) {
                    continue 2;
                }

                // Check if package is currently installed, if so, use installed constraint and skip question.
                if (! isset($this->installedPackages[$package])) {
                    $packagesToInstall[$package] = $this->installedPackages[$package];
                    continue 2;
                }
            }

            $package    = $this->askDependencyQuestion($question, $options);
            $constraint = $options[$package] ?? $constraint = $this->findBestVersionForPackage($package);

            $this->write(\sprintf('Using version <info>%s</info> for <info>%s</info>', $constraint, $package));

            $packages[$package] = $constraint;
        }

        if (\count($packagesToInstall) !== 0) {
            $this->updateComposerJson($packagesToInstall);

            $this->runInstaller(
                $this->updateRootComposerJson($this->composer->getPackage(), $packagesToInstall, true),
                \array_keys($packagesToInstall)
            );
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

//        foreach ($package->getConfiguratorOptions('dependency') as $question => $options) {
//
//        }
    }

    private function getInstalledPackageConstraint($package)
    {
        // Package is currently installed. Add it to root composer.json
        if (! isset($this->installedPackages[$package])) {
            return null;
        }

        $constraint = '^' . $this->installedPackages[$package];

        $this->write(sprintf(
            'Added package <info>%s</info> to composer.json with constraint <info>%s</info>;' .
            ' to upgrade, run <info>composer require %s:VERSION</info>',
            $package,
            $constraint,
            $package
        ));

        return $constraint;
    }

    /**
     *
     *
     * @param string $question
     * @param array  $packages
     *
     * @throws \Exception
     *
     * @return null|string
     */
    private function askDependencyQuestion(string $question, array $packages): ?string
    {
        $ask = \sprintf('<question>%s</question>' . "\n", $question);

        foreach ($packages as $i => $name) {
            $ask .= \sprintf('  [<comment>%d</comment>] %s' . "\n", $i + 1, $name);
        }

        $ask .= '  Make your selection: ';

        do {
            $package = $this->io->askAndValidate(
                $ask,
                function ($input) use ($packages) {
                    $input = \is_numeric($input) ? (int) \trim($input) : 0;

                    if (isset($packages[$input - 1])) {
                        return $packages[$input - 1];
                    }

                    return null;
                }
            );
        } while (! $package);

        return $package;
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
     * @param bool                                   $add
     *
     * @return \Composer\Package\RootPackageInterface
     */
    private function updateRootComposerJson(RootPackageInterface $rootPackage, array $packages, bool $add): RootPackageInterface
    {
        $this->write('Updating root package');

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
     * @param array $packages
     *
     * @return void
     */
    private function updateComposerJson(array $packages): void
    {
        $this->write('Updating composer.json');

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
        $this->write('Running an update to install dependent packages');

        /** @var Installer $installer */
        $installer = new Installer(
            $this->io,
            $this->composer->getConfig(),
            $rootPackage,
            $this->composer->getDownloadManager(),
            $this->composer->getRepositoryManager(),
            $this->composer->getLocker(),
            $this->composer->getInstallationManager(),
            $this->composer->getEventDispatcher(),
            $this->composer->getAutoloadGenerator()
        );

        $installer->setUpdate();
        $installer->setUpdateWhitelist($packages);

        return $installer->run();
    }
}
