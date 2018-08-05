<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Installer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Installer\AbstractInstallationManager;
use Narrowspark\Automatic\OperationsResolver;
use Symfony\Component\Console\Input\InputInterface;

class InstallationManager extends AbstractInstallationManager
{
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
     * Install required and required-dev packages.
     *
     * @param array $requires
     * @param array $devRequires
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\RuntimeException
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException
     * @throws \Exception
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package[]
     */
    public function install(array $requires, array $devRequires = []): array
    {
        $rootPackages = [];

        foreach ($this->getRootRequires() as $link) {
            $rootPackages[\mb_strtolower($link->getTarget())] = (string) $link->getConstraint();
        }

        $oldInstallManager = $this->composer->getInstallationManager();

        $this->addAutomaticInstallationManagerToComposer($oldInstallManager);

        $requiresToInstall    = $this->preparePackage($requires, $rootPackages);
        $devRequiresToInstall = $this->preparePackage($devRequires, $rootPackages);

        if ((\count($requiresToInstall) + \count($devRequiresToInstall)) !== 0) {
            $this->updateComposerJson($requiresToInstall, $devRequiresToInstall, self::ADD);

            $this->runInstaller(
                $this->updateRootComposerJson($requiresToInstall, $devRequiresToInstall, self::ADD),
                \array_keys(\array_merge($requiresToInstall, $devRequiresToInstall))
            );
        }

        $operations = $this->composer->getInstallationManager()->getOperations();

        // Revert to the old install manager.
        $this->composer->setInstallationManager($oldInstallManager);

        return $this->operationsResolver->resolve($operations);
    }

    /**
     * Install required and required-dev packages.
     *
     * @param array $requires
     * @param array $devRequires
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\RuntimeException
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException
     * @throws \Exception
     *
     * @return \Narrowspark\Automatic\Common\Contract\Package[]
     */
    public function uninstall(array $requires, array $devRequires = []): array
    {
    }

    /**
     * Checks if package exists and prepares package version.
     *
     * @param array $requires
     * @param array $rootPackages
     *
     * @return array
     */
    protected function preparePackage(array $requires, array $rootPackages): array
    {
        $toInstall = [];

        foreach ($requires as $packageName => $version) {
            // Check if package variable is a integer
            if (\is_int($packageName)) {
                $packageName = $version;
                $version     = null;
            }

            // Package has been already prepared to be installed, skipping.
            // Package from this group has been found in root composer, skipping.
            if (isset($toInstall[$packageName]) || isset($rootPackages[$packageName])) {
                continue;
            }

            // Check if package is currently installed, if so, use installed constraint and skip question.
            if (isset($this->installedPackages[$packageName])) {
                $version    = $this->installedPackages[$packageName];
                $constraint = \mb_strpos($version, 'dev-') === false ? '^' . $version : $version;

                $toInstall[$packageName] = $constraint;

                $this->io->write(\sprintf(
                    'Added package <info>%s</info> to composer.json with constraint <info>%s</info>;'
                    . ' to upgrade, run <info>composer require %s:VERSION</info>',
                    $packageName,
                    $constraint,
                    $packageName
                ));

                continue;
            }

            if (\in_array($version, ['*', null, ''], true)) {
                $constraint = $this->findVersion($packageName);
            } else {
                $constraint = $version;
            }

            $this->io->writeError(\sprintf('Using version <info>%s</info> for <info>%s</info>', $constraint, $packageName));

            $toInstall[$packageName] = $constraint;
        }

        return $toInstall;
    }
}
