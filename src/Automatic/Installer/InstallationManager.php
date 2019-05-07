<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Installer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer\InstallerEvents;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Narrowspark\Automatic\Common\Installer\AbstractInstallationManager;
use Narrowspark\Automatic\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Prefetcher\Traits\PrefetcherTrait;
use Symfony\Component\Console\Input\InputInterface;

class InstallationManager extends AbstractInstallationManager implements EventSubscriberInterface
{
    use PrefetcherTrait;

    /**
     * A Container instance.
     *
     * @var \Narrowspark\Automatic\Contract\Container
     */
    protected $container;

    /**
     * List of white listed packages.
     *
     * @var array
     */
    private $whiteList = [];

    /**
     * Create a new InstallationManager instance.
     *
     * @param \Composer\Composer                        $composer
     * @param \Narrowspark\Automatic\Contract\Container $container
     */
    public function __construct(Composer $composer, ContainerContract $container)
    {
        $this->container = $container;

        $composer->getEventDispatcher()->addSubscriber($this);

        parent::__construct($composer, $container->get(IOInterface::class), $container->get(InputInterface::class));
    }

    /**
     * Install required and required-dev packages.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package[] $requires
     * @param \Narrowspark\Automatic\Common\Contract\Package[] $devRequires
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\RuntimeException
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException
     *
     * @return void
     */
    public function install(array $requires, array $devRequires = []): void
    {
        $rootPackages = [];

        foreach ($this->getRootRequires() as $link) {
            $rootPackages[\strtolower($link->getTarget())] = (string) $link->getConstraint();
        }

        $requiresToInstall    = $this->preparePackagesToInstall($requires, $rootPackages);
        $devRequiresToInstall = $this->preparePackagesToInstall($devRequires, $rootPackages);

        if ((\count($requiresToInstall) + \count($devRequiresToInstall)) !== 0) {
            $this->updateComposerJson($requiresToInstall, $devRequiresToInstall, self::ADD);

            $this->updateRootComposerJson($requiresToInstall, $devRequiresToInstall, self::ADD);

            $this->whiteList = \array_keys(\array_merge($requiresToInstall, $devRequiresToInstall));
        }
    }

    /**
     * Install required and required-dev packages.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package[] $requires
     * @param \Narrowspark\Automatic\Common\Contract\Package[] $devRequires
     *
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\RuntimeException
     * @throws \Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException
     * @throws \Exception
     *
     * @return void
     */
    public function uninstall(array $requires, array $devRequires): void
    {
        $requires    = $this->preparePackagesToUninstall($requires);
        $devRequires = $this->preparePackagesToUninstall($devRequires);

        $this->updateComposerJson($requires, $devRequires, self::REMOVE);

        $whiteList = \array_merge($requires, $devRequires);

        foreach ($this->localRepository->getPackages() as $localPackage) {
            $mixedRequires = \array_merge($localPackage->getRequires(), $localPackage->getDevRequires());

            foreach ($whiteList as $whitelistPackageName => $version) {
                if (isset($mixedRequires[$whitelistPackageName])) {
                    unset($whiteList[$whitelistPackageName]);
                }
            }
        }

        $this->whiteList = $whiteList;

        $this->updateRootComposerJson($requires, $devRequires, self::REMOVE);
    }

    /**
     * @throws \Exception
     *
     * @return int
     */
    public function run(): int
    {
        $status = $this->runInstaller($this->rootPackage, $this->whiteList);

        if ($status !== 0) {
            $this->io->writeError(\PHP_EOL . '<error>Removal failed, reverting ' . Factory::getComposerFile() . ' to its original content.</error>');

            \file_put_contents($this->jsonFile->getPath(), $this->composerBackup);
        }

        return $status;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            InstallerEvents::PRE_DEPENDENCIES_SOLVING  => [['onPreDependenciesSolving', \PHP_INT_MAX]],
            InstallerEvents::POST_DEPENDENCIES_SOLVING => [['populateFilesCacheDir', \PHP_INT_MAX]],
            PackageEvents::PRE_PACKAGE_INSTALL         => [['populateFilesCacheDir', ~\PHP_INT_MAX]],
            PackageEvents::PRE_PACKAGE_UPDATE          => [['populateFilesCacheDir', ~\PHP_INT_MAX]],
            PluginEvents::PRE_FILE_DOWNLOAD            => 'onFileDownload',
        ];
    }

    /**
     * Checks if package exists and prepares package version.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package[] $requires
     * @param array                                            $rootPackages
     *
     * @return array
     */
    protected function preparePackagesToInstall(array $requires, array $rootPackages): array
    {
        $toInstall = [];

        foreach ($requires as $package) {
            $packageName = $package->getPrettyName();
            $version     = $package->getPrettyVersion();

            // Package has been already prepared to be installed, skipping.
            // Package from this group has been found in root composer, skipping.
            if (isset($toInstall[$packageName]) || isset($rootPackages[$packageName])) {
                continue;
            }

            // Check if package is currently installed, if so, use installed constraint.
            if (isset($toInstall[$packageName])) {
                $version    = $toInstall[$packageName];
                $constraint = \strpos($version, 'dev') === false ? '^' . $version : $version;

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
                $constraint = $this->io->askAndValidate(
                    \sprintf(
                        'Enter the version of <info>%s</info> to require (or leave blank to use the latest version): ',
                        $packageName
                    ),
                    static function ($input) {
                        return \trim($input) ?? false;
                    }
                );

                if ($constraint === false) {
                    $constraint = $this->findBestVersionForPackage($packageName);
                }
            } else {
                $constraint = $version;
            }

            $this->io->writeError(\sprintf('Using version <info>%s</info> for <info>%s</info>', $constraint, $packageName));

            $toInstall[$packageName] = $constraint;
        }

        return $toInstall;
    }

    /**
     * Prepare the array of packages.
     *
     * @param \Narrowspark\Automatic\Common\Contract\Package[] $requires
     *
     * @return array<string, null|string>
     */
    private function preparePackagesToUninstall(array $requires): array
    {
        $preparedRequires = [];

        foreach ($requires as $package) {
            $preparedRequires[$package->getPrettyName()] = $package->getPrettyVersion();
        }

        return $preparedRequires;
    }
}
