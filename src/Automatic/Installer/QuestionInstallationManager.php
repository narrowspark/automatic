<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Installer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Contract\Exception\RuntimeException;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;
use Narrowspark\Automatic\Common\Installer\AbstractInstallationManager;
use Narrowspark\Automatic\OperationsResolver;
use Symfony\Component\Console\Input\InputInterface;

class QuestionInstallationManager extends AbstractInstallationManager
{
    /**
     * List of selected question packages to install.
     *
     * @var array
     */
    private $packagesToInstall = [];

    /**
     * A operations resolver instance.
     *
     * @var \Narrowspark\Automatic\OperationsResolver
     */
    private $operationsResolver;

    /**
     * Create a new ExtraDependencyInstaller instance.
     *
     * @param \Composer\Composer                              $composer
     * @param \Composer\IO\IOInterface                        $io
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Narrowspark\Automatic\OperationsResolver       $operationsResolver
     */
    public function __construct(Composer $composer, IOInterface $io, InputInterface $input, OperationsResolver $operationsResolver)
    {
        parent::__construct($composer, $io, $input);

        $this->operationsResolver = $operationsResolver;
    }

    /**
     * Install selected extra dependencies.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     * @param array                                          $questionMarkedDependencies
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\RuntimeException
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException
     * @throws \Exception
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package[]
     */
    public function install(PackageContract $package, array $questionMarkedDependencies): array
    {
        if (! $this->io->isInteractive() || \count($questionMarkedDependencies) === 0) {
            // Do nothing in no-interactive mode
            return [];
        }

        $rootPackages = [];

        foreach ($this->getRootRequires() as $link) {
            $rootPackages[\mb_strtolower($link->getTarget())] = (string) $link->getConstraint();
        }

        $oldInstallManager = $this->composer->getInstallationManager();

        $this->addAutomaticInstallationManagerToComposer($oldInstallManager);

        foreach ($questionMarkedDependencies as $question => $options) {
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

                    $this->io->write(\sprintf(
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
            $this->updateComposerJson($this->packagesToInstall, [], self::ADD);

            $this->runInstaller(
                $this->updateRootComposerJson($this->packagesToInstall, [], self::ADD),
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
     * Uninstall extra dependencies.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package $package
     * @param array                                          $questionMarkedDependencies
     *
     * @throws \Exception
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package[]
     */
    public function uninstall(PackageContract $package, array $questionMarkedDependencies): array
    {
        $oldInstallManager = $this->composer->getInstallationManager();

        $this->addAutomaticInstallationManagerToComposer($oldInstallManager);

        if (\count($questionMarkedDependencies) !== 0) {
            $this->updateComposerJson(
                \array_merge($questionMarkedDependencies, (array) $package->getConfig('selected-question-packages')),
                [],
                self::REMOVE
            );

            $localPackages = $this->localRepository->getPackages();
            $whiteList     = \array_merge($package->getRequires(), $questionMarkedDependencies);

            foreach ($localPackages as $localPackage) {
                $mixedRequires = \array_merge($localPackage->getRequires(), $localPackage->getDevRequires());

                foreach ($whiteList as $whitelistPackageName) {
                    if (isset($mixedRequires[$whitelistPackageName])) {
                        unset($whiteList[$whitelistPackageName]);
                    }
                }
            }

            $this->runInstaller(
                $this->updateRootComposerJson($questionMarkedDependencies, [], self::REMOVE),
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
     * Returns selected packages from questions.
     *
     * @return array
     */
    public function getPackagesToInstall(): array
    {
        return $this->packagesToInstall;
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
                    /** @codeCoverageIgnoreStart */
                    $input = \is_numeric($input) ? (int) \trim((string) $input) : -1;

                    return $packageNames[$input] ?? null;
                    // @codeCoverageIgnoreEnd
                }
            );
        } while (! $package);

        return $package;
    }
}
